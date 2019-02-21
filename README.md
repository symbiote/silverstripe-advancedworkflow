# Advanced Workflow Module

[![Build Status](https://travis-ci.org/symbiote/silverstripe-advancedworkflow.svg?branch=master)](https://travis-ci.org/symbiote/silverstripe-advancedworkflow)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/symbiote/silverstripe-advancedworkflow/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/symbiote/silverstripe-advancedworkflow/?branch=master)
[![SilverStripe supported module](https://img.shields.io/badge/silverstripe-supported-0071C4.svg)](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/)

## Overview

A module that provides an action / transition approach to workflow, where a
single workflow process is split into multiple configurable states (Actions)
with multiple possible transitions between the actions.

## Requirements

 * SilverStripe Framework and CMS 3.1 or newer
 * (Optional) [Queued Jobs module](https://github.com/nyeholt/silverstripe-queuedjobs) (for embargo/expiry functionality)
 
 Note: The SilverStripe 2.4 version of the module is available from the ss24
 branch of the repository.

## Installation

```
composer require symbiote/silverstripe-advancedworkflow
```

The workflow extension is automatically applied to the `SiteTree` class (if available). 

## Documentation
 - [User guide](docs/en/userguide/index.md)
 - [Developer documentation](docs/en/index.md)

## Contributing

### Translations

Translations of the natural language strings are managed through a third party translation interface, transifex.com. Newly added strings will be periodically uploaded there for translation, and any new translations will be merged back to the project source code.

Please use [https://www.transifex.com/projects/p/silverstripe-advancedworkflow](https://www.transifex.com/projects/p/silverstripe-advancedworkflow) to contribute translations, rather than sending pull requests with YAML files.
