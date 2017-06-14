# SilverStripe Schema Module

The SilverStripe Schema Module allows you to add structured data to your website based on the schema.org vocabulary. Structured data is currently formatted as JSON-LD but could be modified to be returned in other formats.

The module provides a CMS interface allowing website maintainers to create dynamically populated structured data which represents objects represented on website/webpages.

## Features
* Granular configuration of enabled schemas, extended DataObjects and class properties & methods which can be used to dynamically populate the structured data
* Configure a default schema for each extended DataObject, using dynamic values, in order to quickly markup multiple records (i.e. every Page, Members or File object)
* Populate schema properties with dynamic values (i.e. `Member::getName()`), static (i.e. 'John Smith') or nested schema instances
* Add, Update or Disable class properties and methods used to populate structured data dynamically
* Easy, non-intrusive integration with existing SilverStripe templates
* Ability to configure which schemas are enabled via the CMS (for end-users) and programmatically (for developers) 

## Version
`0.1.0-alpha` - This module is in use in a number of production environments, so should be relativly stable however, it has been built with only Quadra's requirements in mind and is yet to be reviewed by any external parties. The current version is therefore an alpha, rather than beta or stable, to allow for potentially breaking changes to come in via [pull requests](https://github.com/Quadra-Digital/silverstripe-schema/pulls).

## Requirements
[`silverstripe/cms: ~3.5`](https://github.com/silverstripe/silverstripe-cms/tree/3.5)

Untested but expected to work with [`silverstripe/cms: ~3.2`](https://github.com/silverstripe/silverstripe-cms/tree/3.2.0)

## Installation
### Composer
```
composer require quadra-digital/silverstripe-schema
```

### Manual
* Download the code base by either cloning this repository or downloading the provided .zip file
* Download the dependencies listed in requirements
* You can name the module directory whatever you like, we recommend silverstripe-schema
* Place the module directory in the sites web root (i.e. at the same level as /mysite)

## Configuration
* [optional - see the [sample config.yml file](/docs/en/examples/_config/config.yml) for an example] Using your mysite config.yml configure:
    * Which of your custom DataObjects get extended to make use of schemas
    * The schemas enabled by default
    * The class properties and methods used to populate structured data dynamically
* Make sure the /data-sources/ directory gives read & write permission to the web server (i.e apache/httpd)
* Run a /dev/build and /?flush=all against the website
* Log in to your websites CMS and checkout the 'Schemas' tab where you can enable additional schemas from schema.org and set up a default schema for each relevant DataObject
* All DataObjects which have been extended (as per above) will have an additional tab in the CMS of 'Schemas' where you can add and overload schemas for that individual DataObject
* Add `$getStructuredData()` in your Page.ss template and other relevant templates, giving consideration to scope. See [/docs/en/examples/templates/](/docs/en/examples/templates/) for examples.

## License
This module uses the BSD 3-Clause license. See the [LICENCE.md](/LICENCE.md) file for the full license.

## Copyright
Copyright (c) 2017, [Quadrahedron Limited](https://www.quadradigital.co.uk)
All rights reserved.

##Roadmap
### To Do
* Provide detailed documentation in [/docs/en](/docs/en)
* Look into performance impact of structured data generation, consider caching techniques and other performance improvements
* Refactor codebase for PSR-2 compliance
* Allow for multiple nested schemas rather than just one, allowing for CMS configuration of things like Organisation:Employes[Staff1, Staff2, Staff3] rather than only programmatically
* Include JSON and schema.org validation of resulting output
* Include 'diff' process to identify new/updated/deprecated schema properties during schema.org sync process and notify end users of newly available or removed options

### Known Issues
None

[You can report an issue here](https://github.com/Quadra-Digital/silverstripe-schema/issues)

## Contact
This module is built by [Quadra Digital](https://www.quadradigital.co.uk) and has been made open source for free, we are unlikely to be able to offer much support however if you have any queries regarding usage, licensing, bugs or improvements please use one of the appropriate contact below.
#### Technical
Joe Harvey <[joe.harvey@quadradigital.co.uk](mailto:joe.harvey@quadradigital.co.uk)>
#### Administrative
Peter Foster <[peter.foster@quadradigital.co.uk](mailto:peter.foster@quadradigital.co.uk)>

