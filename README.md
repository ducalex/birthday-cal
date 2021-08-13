# birthday-cal
This is a sabre/dav plugin to generate iCalendars from an addressbook's birthdays.

# Usage in Baikal
1. Place BirthdayCalPlugin.php in `vendor/sabre/dav/lib/CardDAV`
2. Open `Core/Frameworks/Baikal/Core/Server.php`, locate the `if ($this->enableCardDAV)` block and add this line inside it:
   `$this->server->addPlugin(new \Sabre\CardDAV\BirthdayCalPlugin());`

You will now have a "birthdays" link next to export button when browsing your address books. Or type the URL yourself: https://example.com/dav.php/addressbooks/username/default/?calendar

# Future Plans
I would like to create a readonly CalDAV birthday calendar instead of ICS export. 

This would allow simpler discovery and synchronization and a better chance of being accepted upstream...

# Acknowledgements
The plugin structure was copied from VCFExportPlugin.php
