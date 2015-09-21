===========================================================
Breaking: #56951 - Remove unused methods in PagePositionMap
===========================================================

Description
===========

Remove unused methods in PagePositionMap


Impact
======

A fatal error will be thrown if one of the removed methods is used.
The removed methods are:

``insertQuadLines``
``JSimgFunc``


Affected Installations
======================

Installations that use one of the removed methods.


Migration
=========

Use proper styling for a tree list.