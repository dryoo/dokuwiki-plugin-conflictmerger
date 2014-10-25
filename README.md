dokuwiki-plugin-conflictmerger
==============================

dokuwiki-plugin-conflictmerger by Daniel Calviño Sánchez

Description
-----------

If a user starts editing a page and in the meantime another user edits and save the same page, when the first user tries to save its page a conflict arises, as it was changed since the edition started. In DokuWiki, when there is a conflict the user has only two choices: overwrite the latest revision of the page or keep it (losing the changes made by the user).

This plugin makes DokuWiki behave like MediaWiki: when there is a conflict, the conflict is automatically solved (when possible). A conflict can be automatically solved if the changes are independent (for example, one user changes line 1 and another user changes line 3). This plugin also adds the option to not only save or cancel, but also edit again when a conflict is found. See Behaviour section for further information.
