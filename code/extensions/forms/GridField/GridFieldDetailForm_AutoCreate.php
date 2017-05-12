<?php

/**
 * Extends the standard detail form (add, edit and view) to mimic the behaviour
 * of {@link SiteTree} and {@link CMSPageAddController} i.e. when the detail
 * form is used to add a new record, using the URL format <FormURL>/field/<GridFieldName>/item/new,
 * automatically create and save that record (meaning it has an associated DB
 * record and ID) and redirect to the new records edit form. This means we can
 * use GridFieldAddExistingAutocompleter straight away instead of having to manually
 * save the new record for the first time.
 */
class GridFieldDetailForm_AutoCreate extends GridFieldDetailForm {

    /**
     *
     * @param type $gridField
     * @param type $request
     * @return GridFieldDetailForm_ItemRequest
     */
    public function handleItem($gridField, $request) {

        // Our getController could either give us a true Controller, if this is the top-level GridField.
        // It could also give us a RequestHandler in the form of GridFieldDetailForm_ItemRequest if this is a
        // nested GridField.
        $requestHandler = $gridField->getForm()->getController();
        if(strtolower($request->param('ID') === 'new')) { // Adding a new record
            $record = Object::create($gridField->getModelClass());
            $recordID = $record->write();
            $list = $gridField->getList();
            if($list instanceof HasManyList || $list instanceof ManyManyList) {
                $list->add($record);
            }
            $url = preg_replace('/\/new(\/)?$/', "/$recordID", $request->getURL());
            return Controller::curr()->redirect($url);
        } else if(is_numeric($request->param('ID'))) { // Editing or viewing an existing record
            $record = $gridField->getList()->byId($request->param("ID"));
        } else { // Any other unexpected scenario
            $record = Object::create($gridField->getModelClass());
        }

        $class = $this->getItemRequestClass();

        $handler = Object::create($class, $gridField, $this, $record, $requestHandler, $this->name);
        $handler->setTemplate($this->template);

        // if no validator has been set on the GridField and the record has a
        // CMS validator, use that.
        if(!$this->getValidator() && (method_exists($record, 'getCMSValidator') || $record instanceof Object && $record->hasMethod('getCMSValidator'))) {
            $this->setValidator($record->getCMSValidator());
        }

        return $handler->handleRequest($request, DataModel::inst());

    }

}