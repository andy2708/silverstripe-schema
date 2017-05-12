<?php

class SchemaBuildExtension extends DevBuildController {

    private static $url_handlers = array(
        '' => 'build'
    );

    private static $allowed_actions = array(
        'build'
    );

    public function build($request) {

        parent::build($request);

        UpdateSchemasTask::updateDataSources();

    }

}