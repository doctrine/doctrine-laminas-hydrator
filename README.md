# zend-doctrine-hydrator

[![Build Status](https://travis-ci.org/webimpress/zend-doctrine-hydrator.svg?branch=master)](https://travis-ci.org/webimpress/zend-doctrine-hydrator)
[![Coverage Status](https://coveralls.io/repos/github/webimpress/zend-doctrine-hydrator/badge.svg?branch=master)](https://coveralls.io/github/webimpress/zend-doctrine-hydrator?branch=master)

This library provides Doctrine Hydrators for Zend Framework application. 

## Installation

Run the following to install this library:

```bash
$ composer require webimpress/zend-doctrine-hydrator
```

## Usage

You can use `Zend\Doctrine\Hydrator\DoctrineObject` hydrator with your
[`zend-form`](https://docs.zendframework.com/zend-form/):

```php
$hydrator = new DoctrineObject($objectManager, $byValue);

$myForm = new MyForm();
$myForm->setHydrator($hydrator);
```

To initialize `DoctrineObject` hydrator you need to pass your `ObjectManager`
(`EntityManager`) instance and flag, if hydrator should use entity's public
API (by default it is `true`).
