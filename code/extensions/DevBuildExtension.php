<?php

class DevBuildExtension extends DevBuildController {

    private static $url_handlers = array(
        '' => 'build'
    );

    private static $allowed_actions = array(
        'build'
    );

    public function build($request) {

        parent::build($request);

        /**
         * On /dev/build only download data sources if they are missing, do not
         * update on every single /dev/build
         */
        if(!file_exists("../" . Schema::getVocabularyFilePath())) {
            UpdateDataSourcesTask::updateDataSources();
        }

    }

}