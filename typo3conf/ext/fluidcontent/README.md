<img src="https://fluidtypo3.org/logo.svgz" width="100%" />

Fluidcontent: Fluid Content Elements
====================================

[![Build Status](https://img.shields.io/travis/FluidTYPO3/fluidcontent.svg?style=flat-square&label=package)](https://travis-ci.org/FluidTYPO3/fluidcontent) [![Coverage Status](https://img.shields.io/coveralls/FluidTYPO3/fluidcontent/development.svg?style=flat-square)](https://coveralls.io/r/FluidTYPO3/fluidcontent) [![Build status](http://img.shields.io/badge/documentation-online-blue.svg?style=flat-square)](https://fluidtypo3.org/documentation/templating-manual/introduction.html) [![Build Status](https://img.shields.io/travis/FluidTYPO3/fluidtypo3-testing.svg?style=flat-square&label=framework)](https://travis-ci.org/FluidTYPO3/fluidtypo3-testing) [![Coverage Status](https://img.shields.io/coveralls/FluidTYPO3/fluidtypo3-testing/master.svg?style=flat-square)](https://coveralls.io/r/FluidTYPO3/fluidtypo3-testing)

## What does it do?

EXT:fluidcontent lets you write custom content elements based on Fluid templates. Each content element and its possible settings
are contained in a single Fluid template file. Whole sets of files can be registered and placed in its own tab in the new content
element wizard, letting you group your content elements. The template files are placed in a very basic extension. The _Nested
Content Elements_ support that Flux enables is utilized to make content elements which can contain other content elements.

## Why use it?

**Fluid Content** is a fast, dynamic and extremely flexible way to create content elements. Not only can you use Fluid, you can
also create dynamic configuration options for each content element using Flux - in the exact same way as done in the Fluid Pages
extension; see https://github.com/FluidTYPO3/fluidpages.

## How does it work?

Fluid Content Elements are registered through TypoScript. The template files are then processed to read various information about
each template, which is then made available for use just as any other content element type is used.

When editing the content element, Flux is used to generate the form section which lets content editors configure variables which
become available in the template. This allows completely dynamic variables (as opposed to adding extra fields on the `tt_content`
table and configuring TCA for each added column).

View the [online templating manual](https://fluidtypo3.org/documentation/templating-manual/introduction.html) for more information.
