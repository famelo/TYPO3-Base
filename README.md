# TYPO3 Base


## Installation

1. clone repository

```
git clone git@github.com:famelo/TYPO3-Base.git
```

2. create database and import ```typo3conf/database.sql```

3. copy ```typo3conf/AdditionalConfiguration.php.example``` to ```typo3conf/AdditionalConfiguration.php``` and set configuration for local machine

4. login with ```admin:password``` and change the admin password


### Structure of the template Extension

```
Resources/Public              // Contains all css, js, images, fonts, etc
  - Media/*                   // Media files like images, etc
  - Components/*              // External Libraries and Components
  - Styles/Base.less          // General Styling mainly focused towards desktop
  - Styles/Mobile.less        // Special styling for mobile devices
  - Styles/Tablet.less        // Special styling for tablet devices
  - Styles/Main.less          // ties all the other styles together
  - Scripts/Main.js           // Place to put custom JS code

Configuration/TypoScript/Setup  // Contains all PHP code to modify WP behavior
  - Bootstrap.ts                // Page configuration and CSS/JS Header includes
  - Language.ts                 // Configuration for Language
  - Extensions.ts               // Place to put TypoScipt for different Extensions

Resources/Private               // Templates
  - Templates/Page              // Page Templates based on fluidpages
  - Templates/Content           // Content Elements based on fluidcontent
  - Layouts                     // HTML that's the same on various page templates
  - Partials                    // Reusable Parts
  - Extensions/[ExtName]/...    // Place to put altered extension templates
```