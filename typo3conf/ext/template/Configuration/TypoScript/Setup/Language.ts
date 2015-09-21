config{
	### Language and locallization

	# this must be set to the language of the site
	language = {$config.language}

	# make sure to set this to the language your site is in
	locale_all = {$config.locale_all}.UTF-8
	htmlTag_langKey = {$config.locale_all}

	# Sets the default system language
	sys_language_uid = 0

	# How to handle locallization
	sys_language_mode = content_fallback

	# Records that are not locallized till be hidden
	sys_language_overlay = hideNonTranslated

	# Setting up the language variable "L" to be passed along with links
	config.linkVars = L
}