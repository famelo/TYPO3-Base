========================================================================
Breaking: #68193 - Drop removeLoginpagesWithContentHash from Indexer.php
========================================================================

Description
===========

The method ``\TYPO3\CMS\IndexedSearch\Indexer::removeLoginpagesWithContentHash()`` was not used within the core since 6.0
and has been removed.


Impact
======

Calling ``\TYPO3\CMS\IndexedSearch\Indexer::removeLoginpagesWithContentHash()`` will throw a fatal error.


Affected Installations
======================

All installations with third party code using the mentioned method.


Migration
=========

No migration is available.