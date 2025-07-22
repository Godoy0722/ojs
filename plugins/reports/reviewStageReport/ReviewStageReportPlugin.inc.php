<?php

/**
 * @file plugins/reports/reviewStageReport/ReviewStageReportPlugin.inc.php
 *
 * Copyright (c) 2023 Your Name
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewStageReportPlugin
 * @ingroup plugins_reports_reviewStageReport
 *
 * @brief Review Stage Report plugin
 */

import('lib.pkp.classes.plugins.ReportPlugin');
import('lib.pkp.classes.submission.reviewRound.ReviewRoundDAO');
import('classes.journal.SectionDAO');

class ReviewStageReportPlugin extends ReportPlugin {

	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		$this->addLocaleData();
		return $success;
	}

	/**
	 * @copydoc Plugin::getName()
	 */
	function getName() {
		return 'ReviewStageReportPlugin';
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.reports.reviewStageReport.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.reports.reviewStageReport.description');
	}

	/**
	 * @copydoc ReportPlugin::display()
	 */
	function display($args, $request) {
		$context = $request->getContext();
		$contextId = $context->getId();

		// Set up CSV file
		header('content-type: text/comma-separated-values');
		header('content-disposition: attachment; filename=review-stage-report-' . date('Ymd') . '.csv');
		$fp = fopen('php://output', 'wt');
		// Add BOM (byte order mark) to fix UTF-8 in Excel
		fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

		// Define columns for the CSV file
		$columns = [
			__('plugins.reports.reviewStageReport.submissionId'),
			__('plugins.reports.reviewStageReport.submissionTitle'),
			__('plugins.reports.reviewStageReport.submissionDate'),
			__('plugins.reports.reviewStageReport.lastModified'),
			__('plugins.reports.reviewStageReport.section'),
			__('plugins.reports.reviewStageReport.round'),
			__('plugins.reports.reviewStageReport.status')
		];

		fputcsv($fp, $columns);

		/** @var SubmissionDAO $submissionDao */
		$submissionDao = DAORegistry::getDAO('SubmissionDAO');

		/** @var SectionDAO $sectionDao */
		$sectionDao = DAORegistry::getDAO('SectionDAO');

		$sectionCache = [];
		$batchSize = 100;
		$offset = 0;

		import('lib.pkp.classes.db.DBResultRange');
		import('lib.pkp.classes.submission.reviewRound.ReviewRound');

		do {
			$sql = "
				SELECT
					s.submission_id AS submissionId,
					COALESCE(psl.setting_value, psen.setting_value, ps.setting_value) AS submissionTitle,
					DATE(s.date_submitted) AS submissionDate,
					DATE(s.last_modified) AS lastModified,
					p.section_id AS sectionId,
					rr.round AS round,
					rr.status AS status
				FROM submissions s
				JOIN publications p ON p.publication_id = s.current_publication_id
				LEFT JOIN publication_settings ps ON (ps.publication_id = p.publication_id AND ps.setting_name = ? AND ps.locale = '')
				LEFT JOIN publication_settings psl ON (psl.publication_id = p.publication_id AND psl.setting_name = ? AND psl.locale = ?)
				LEFT JOIN publication_settings psen ON (psen.publication_id = p.publication_id AND psen.setting_name = ? AND psen.locale = 'en_US')
				JOIN (
					SELECT submission_id, MAX(round) AS max_round
					FROM review_rounds
					WHERE stage_id = ?
					GROUP BY submission_id
				) rrmax ON rrmax.submission_id = s.submission_id
				JOIN review_rounds rr ON rr.submission_id = rrmax.submission_id AND rr.round = rrmax.max_round AND rr.stage_id = ?
				WHERE s.context_id = ? AND s.stage_id = ? AND s.status NOT IN (3, 4)
				ORDER BY s.submission_id
			";
			$contextLocale = $context->getPrimaryLocale() ?? '';
			$params = [
				'title',
				'title',
				$contextLocale,
				'title',
				WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
				WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
				(int)$contextId,
				WORKFLOW_STAGE_ID_EXTERNAL_REVIEW
			];
			$result = $submissionDao->retrieveRange($sql, $params, new DBResultRange($batchSize, 1, $offset));

			$rows = [];
			foreach ($result as $dbRow) {
				$rows[] = (array)$dbRow;
			}
			if (empty($rows)) break;
			foreach ($rows as $row) {
				$sectionId = $row['sectionId'];
				if (!isset($sectionCache[$sectionId])) {
					$sectionObj = $sectionDao->getById($sectionId, $contextId);
					$sectionCache[$sectionId] = $sectionObj ? $sectionObj->getLocalizedTitle() : '';
				}
				$frow = [
					$row['submissionId'],
					$row['submissionTitle'],
					$row['submissionDate'],
					$row['lastModified'],
					$sectionCache[$sectionId],
					$row['round'],
					$this->_getStatusLabel($row['status'])
				];
				fputcsv($fp, $frow);
			}
			$offset += $batchSize;
		} while (true);

		fclose($fp);
	}

	/**
	 * Return the corresponding label to the revision status
	 *
	 * @param $statusId int
	 * @return string
	 */
	private function _getStatusLabel($statusId) {
		import('lib.pkp.classes.submission.reviewRound.ReviewRound');
		switch ($statusId) {
			case REVIEW_ROUND_STATUS_REVISIONS_REQUESTED:
				return __('editor.submission.roundStatus.revisionsRequested');
			case REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED:
				return __('editor.submission.roundStatus.revisionsSubmitted');
			case REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW:
				return __('editor.submission.roundStatus.resubmitForReview');
			case REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED:
				return __('editor.submission.roundStatus.submissionResubmitted');
			case REVIEW_ROUND_STATUS_SENT_TO_EXTERNAL:
				return __('editor.submission.roundStatus.sentToExternal');
			case REVIEW_ROUND_STATUS_ACCEPTED:
				return __('editor.submission.roundStatus.accepted');
			case REVIEW_ROUND_STATUS_DECLINED:
				return __('editor.submission.roundStatus.declined');
			case REVIEW_ROUND_STATUS_PENDING_REVIEWERS:
				return __('editor.submission.roundStatus.pendingReviewers');
			case REVIEW_ROUND_STATUS_PENDING_REVIEWS:
				return __('editor.submission.roundStatus.pendingReviews');
			case REVIEW_ROUND_STATUS_REVIEWS_READY:
				return __('editor.submission.roundStatus.reviewsReady');
			case REVIEW_ROUND_STATUS_REVIEWS_COMPLETED:
				return __('editor.submission.roundStatus.reviewsCompleted');
			case REVIEW_ROUND_STATUS_REVIEWS_OVERDUE:
				return __('editor.submission.roundStatus.reviewOverdue');
			case REVIEW_ROUND_STATUS_PENDING_RECOMMENDATIONS:
				return __('editor.submission.roundStatus.pendingRecommendations');
			case REVIEW_ROUND_STATUS_RECOMMENDATIONS_READY:
				return __('editor.submission.roundStatus.recommendationsReady');
			case REVIEW_ROUND_STATUS_RECOMMENDATIONS_COMPLETED:
				return __('editor.submission.roundStatus.recommendationsCompleted');
			default:
				return __('submission.review.status.awaitingResponse');
		}
	}
}
