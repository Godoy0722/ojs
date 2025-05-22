# Review Stage Report Plugin

This plugin for OJS 3.3.0 generates a CSV report of all submissions in the review stage.

## Description

The Review Stage Report plugin provides journal managers and editors with a tool to export details about submissions currently in the review stage as a CSV file.

## Generated Report Fields

The report includes the following information:
- Submission ID
- Submission Title
- Submission Date (without time)
- Date of the last submission modification
- Section
- Review round number
- Review round status

## Requirements

This plugin requires OJS 3.3.0 or higher.

## Installation

1. Download the plugin from the plugin gallery or GitHub.
2. Extract the plugin to the `plugins/reports/reviewStageReport` directory.
3. Enable the plugin through the journal's website management interface.

## Usage

1. Log in as Journal Manager
2. Go to Tools > Reports > Review Stage Report
3. Click on "Generate Report" to download the CSV file
