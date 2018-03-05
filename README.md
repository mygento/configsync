# Magento 2 Configuration Sync

A module to store Magento configuration with multiple environments in the version control


[![Build Status](https://travis-ci.org/mygento/configsync.svg?branch=m2)](https://travis-ci.org/mygento/configsync)
[![Latest Stable Version](https://poser.pugx.org/mygento/module-configsync/v/stable)](https://packagist.org/packages/mygento/module-configsync)
[![Total Downloads](https://poser.pugx.org/mygento/module-configsync/downloads)](https://packagist.org/packages/mygento/module-configsync)

## File Syntax

The configuration values are stored in a YAML file.  The format of the file is as follows:

    environment:
        scope_key:
           path: value

For example:

    production:
        default:
            web/secure/base_url: https://domain.com/
            web/secure/use_in_frontend: 1
    development:
        default:
            web/secure/base_url: https://domain1.com/
        stores-1:
            web/secure/use_in_frontend: 0

Valid scope keys are:

* default
* stores-`$id`
* websites-`$id`

## Usage

    php bin/magento setup:config:sync [options] [--] <env> <config_yaml_file>

 Arguments:
 * **env** - environment for import.
 * **config_yaml_file** - the YAML file containing the configuration settings.

 Options:
 * **--detailed** - display detailed information (1 - display, otherwise - not display).
