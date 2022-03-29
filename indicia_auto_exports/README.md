# Indicia Auto Exports

A module that automates periodic generation of Darwin Core Archive or other format exports from
Indicia data.

## Installation

Copy the module folder to /modules/custom then install as normal.

Currently requires records to be associated with groups (activities) so you will need to create
groups on the site to prepare exports for. The **Recording groups > Create or edit a group** form
allows this.

## Usage

Visit /form/published-group-metadata to fill in metadata form for an export.

Completed metadata forms will cause Drupal's background cron job to prepare export files when they
are due. They are prepared in Darwin Core Archive format and can be found at
/sites/default/files/indicia/exports/export-group-<group_id>.zip.