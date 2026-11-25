# Admidio Organization Switcher

An entry *Switch organization* in the profile menu with which a member of several organizations
changes to another one without logging out. Only organizations in which the user has an active
membership are offered – the same ones the login dialog would allow. With "remember me" the
remembered login moves to the new organization as well.

The entry is only shown to users who are members of more than one organization. The plugin has no
settings.

Requires Admidio 6.0 or later and PHP 8.2 or later. The entry uses the filter
`page_user_menu_items` of the Admidio core; a core without that filter gets the entry put into the
profile menu in the browser.

## License

GNU General Public License v2.0 only, see `LICENSE`.
