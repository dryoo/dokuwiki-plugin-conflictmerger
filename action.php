<?php
/**
 * Conflict Merger Plugin for Dokuwiki
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Daniel Calviño Sánchez <danxuliu@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

require_once(DOKU_INC.'inc/html.php');
require_once(DOKU_INC.'inc/infoutils.php');
require_once(DOKU_INC.'inc/pageutils.php');

if (!defined('NL')) define('NL',"\n");

/**
 * Action plugin for Dokuwiki to automatically merge changes with latest
 * revision when saving a document.
 *
 * This plugin intercepts 'edit' and 'save' actions and redirects them to a
 * custom action, 'conflictSolving', when needed. That happens when some changes
 * were made to the document while it was being edited (being it a pure edition,
 * or a conflict solving page).
 *
 * When a 'conflictSolving' action is received, a conflict solving page is
 * shown. If the conflict could be solved, the text being edited and the latest
 * version of the document are merged. Otherwise, no merging happens (when the
 * changes conflict, or when the solving failed). The page also shows the
 * differences between versions, and buttons to save the text, make further
 * editions or cancel the edition.
 *
 * The edit form is bypassed to use the text already being worked on (for
 * example, editting a conflict not mergeable), instead of the latest version of
 * the page.
 *
 * It uses diff3 command. The path to it can be configured through the
 * configuration plugin.
 */
class action_plugin_conflictmerger extends DokuWiki_Action_Plugin {

    /**
     * Returns the information about conflictmerger plugin.
     *
     * @return The information about conflictmerger plugin.
     */
    function getInfo() {
        return array(
                'author' => 'Daniel Calviño Sánchez',
                'email'  => 'danxuliu@gmail.com',
                'date'   => @file_get_contents(DOKU_PLUGIN.'conflictmerger/VERSION'),
                'name'   => 'Conflict Merger Plugin (action component)',
                'desc'   => 'Solves, when possible, edition conflicts automatically merging the changes using diff3',
                'url'    => 'http://wiki.splitbrain.org/plugin:conflictmerger',
                );
    }

    /**
     * Registers handlers for several events.
     */
    function register(Doku_Event_Handler $contr) {
        $contr->register_hook(
                'ACTION_ACT_PREPROCESS',
                'BEFORE',
                $this,
                'handle_action_act_preprocess',
                array()
                );
        $contr->register_hook(
                'TPL_ACT_UNKNOWN',
                'BEFORE',
                $this,
                'handle_tpl_act_unknown',
                array()
                );
        $contr->register_hook(
                'HTML_EDITFORM_OUTPUT',
                'BEFORE',
                $this,
                'handle_html_editform_output',
                array()
                );
    }

    /**
     * Fires "conflictSolving" events when needed.
     * A conflict appears in edit and save actions when the page was modified
     * after the edit or conflict forms were shown. That is, even if the page is
     * modified again while the user is looking to a conflict form, a new
     * conflict form will appear informing the user about it.
     *
     * Also, note the following scenario: user A starts editing a page, user B
     * edits and saves the page, and user A saves the page. A conflict form will
     * be shown to user A, as the page was changed while it was being edited.
     * Now, the user A decides to further edit the page.
     *
     * If the merge was successfull, when the user hit save button in the edit
     * form, the text will be directly saved (provided no other user changes the
     * page in the meantime). That is, now the revision to check conflicts
     * against is the revision the text was merged with, instead of the revision
     * of the page when the fresh edition was started.
     *
     * However, it is different in the case of an unsuccessful merge. In that
     * case, as the contents weren't merged, the revision to check conflicts
     * against is the revision of the page when the fresh edition was started.
     * So even if the text is edited to avoid conflicts, a conflict form will
     * appear, although a successful one. Once a successful merge form appears,
     * the revision to check conflicts against is updated to the latest revision
     * of the page.
     *
     * @param event The TPL_ACT_UNKNOWN event.
     * @param param The parameters of the event.
     */
    function handle_action_act_preprocess(&$event, $param) {
        //$event->data action may come as an array, so it must be cleaned
        $action = $this->cleanAction($event->data);

        if ($action != 'edit' && $action != 'save') {
            return;
        }

        global $DATE;
        global $INFO;

        if ($DATE == 0 || $INFO['lastmod'] <= $DATE) {
            return;
        }

        if ($action == 'edit' && $_REQUEST['conflictDate'] == $INFO['lastmod']) {
            return;
        }

        if ($action == 'save' && $_REQUEST['conflictDate'] == $INFO['lastmod']) {
            $DATE = $_REQUEST['conflictDate'];
            return;
        }

        $event->data = 'conflictSolving';
        $event->preventDefault();
    }

    /**
     * Handles "conflictSolving" actions.
     * Shows the conflict solving area and prevents the default handling of the
     * event.
     *
     * @param event The TPL_ACT_UNKNOWN event.
     * @param param The parameters of the event.
     */
    function handle_tpl_act_unknown(&$event, $param) {
        if ($event->data != 'conflictSolving') {
            return;
        }

        global $PRE;
        global $SUF;
        global $TEXT;

        $this->html_conflict_solving(con($PRE,$TEXT,$SUF));
        $event->preventDefault();
    }

    /**
     * Bypasses the text to be edited set by Dokuwiki when needed.
     * When edit form is created, the text is set to the content of the latest
     * revision of the page. However, in this plugin the edit page can also be
     * shown from the conflict solving page, to further edit the text after a
     * conflict was detected.
     *
     * In those cases, that is, when wikitext parameter is already set, the text
     * in the edit form is changed to the text the user is working on instead of
     * the contents of the latest revision of the page.
     *
     * @param event The HTML_EDITFORM_OUTPUT event.
     * @param param The parameters of the event.
     */
    function handle_html_editform_output(&$event, $param) {
        global $_POST;
        global $INFO;

        if (isset($_POST['wikitext'])) {
            $attr = array('tabindex'=>'1');
            $wr = $INFO['writable'] && !$INFO['locked'];
            if (!$wr) $attr['readonly'] = 'readonly';

            $position = $event->data->findElementById("wiki__text");
            $wikitext = form_makeWikiText(cleanText($_POST['wikitext']), $attr);
            $event->data->replaceElement($position, $wikitext);
        }
    }

    /**
     * Got from Dokuwiki 2008-05-05 (inc/actions.php, function act_clean)
     *
     * Cleans an action.
     * Some actions may come as an array due to being created like "do[action]".
     * It returns the cleaned action as a single string with just the action.
     *
     * @param action The action to clean.
     * @return The cleaned action.
     * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
     * @author     Andreas Gohr <andi@splitbrain.org>
     */
    function cleanAction($action) {
        if (is_array($action)) {
            list($action) = array_keys($action);
        }

        $action = strtolower($action);
        $action = preg_replace('/[^1-9a-z_]+/', '', $action);

        return $action;
    }

    /**
     * Shows a conflict solving page.
     * The page consists of an informative text explaining whether the text
     * could be merged or not, the differences between the latest revision of
     * the page and the text being edited, and a group of buttons to save the
     * changes, further edit the text or cancel all the changes.
     *
     * If the execution of diff3 fails, it reverts to standard behaviour and the
     * page shown is the default Dokuwiki conflict page.
     *
     * @param text The current text.
     */
    function html_conflict_solving($text) {
        global $_REQUEST;
        global $DATE;
        global $ID;
        global $INFO;
        global $lang;
        global $SUM;

        if ($this->merge($ID, rawWiki($ID, $DATE), $text, rawWiki($ID, ''), $result)) {
            print $this->locale_xhtml('conflict-solved');

            $text = $result;
            $DATE = $INFO['lastmod'];
            $conflictDate = 0;
        } else if ($result != '') {
            print $this->locale_xhtml('conflict-unsolved');

            $conflictDate = $INFO['lastmod'];
        } else {
            html_conflict($text, $SUM);
            html_diff($text, false);
            return;
        }

        html_diff($text, false);

        print '<div class="centeralign">'.NL;
        $form = new Doku_Form('dw__editform');
        $form->addHidden('id', $ID);
        $form->addHidden('date', $DATE);
        $form->addHidden('conflictDate', $conflictDate);
        $form->addHidden('wikitext', $text);
        $form->addHidden('summary', $SUM);
        $form->addElement(form_makeButton('submit', 'save', $lang['btn_save'], array('accesskey'=>'s')));
        $form->addElement(form_makeButton('submit', 'edit', $lang['btn_edit'], array('accesskey'=>'e')));
        $form->addElement(form_makeButton('submit', 'cancel', $lang['btn_cancel']));
        html_form('conflict', $form);
        print '</div>'.NL;
    }

    /**
     * Adapted from MediaWiki 1.13.2. (includes/GlobalFunctions.php)
     *
     * @license  You may copy this code freely under the conditions of the GPL.
     *
     * Attempts to merge differences between three texts.
     *
     * It merges the changes happened from "old" to "yours" with "mine", and stores
     * the merged text in "result". If the changes happened from "old" to "yours"
     * overlap with those happened from "old" to "mine" there is a conflict, and the
     * changes can't be automatically merged. That is, it can only merge changes
     * happened in separated areas of the texts ("old" is a previous version of both
     * "mine" and "yours").
     *
     * Differences are computed in a line by line basis. That is, changes in the
     * same line, even if they don't overlap, are seen as a conflict.
     *
     * Merging is done using diff3 executable. It can be configured through 'diff3'
     * plugin configuration key. Temporary files containing old, mine and yours
     * texts are created to be used with diff3.
     *
     * Further information about how merging is done can be got in diff3 info page.
     *
     * Even when a conflict is detected, result contains some merging attempt. Only
     * when a failure happened (for example, if diff3 wasn't found), result is
     * empty.
     *
     * @param id The id of the text to merge.
     * @param old The base text.
     * @param mine The first modified text.
     * @param yours The second modified text.
     * @param result The merged text, if any.
     * @return True for a clean merge and false for failure or conflict.
     */
    function merge( $id, $old, $mine, $yours, &$result ) {
        $diff3 = $this->getConf('diff3');

        $result = '';

        # This check may also protect against code injection in
        # case of broken installations.
        if ( !isset($diff3) || !file_exists( $diff3 ) ) {
            msg( "diff3 not found\n", -1 );
            return false;
        }

        # Make temporary files
        //TODO Is there any function to create temporary files? Modify unit tests as necessary
        $baseName = wikiFN( $id );
        $myTextFile = fopen( $myTextName = ($baseName . '-merge-mine'), 'w' );
        $oldTextFile = fopen( $oldTextName = ($baseName . '-merge-old'), 'w' );
        $yourTextFile = fopen( $yourTextName = ($baseName .  '-merge-your'), 'w' );

        fwrite( $myTextFile, $mine ); fclose( $myTextFile );
        fwrite( $oldTextFile, $old ); fclose( $oldTextFile );
        fwrite( $yourTextFile, $yours ); fclose( $yourTextFile );

        # Check for a conflict
        $cmd = $diff3 . ' -a --overlap-only ' .
            escapeshellarg( $myTextName ) . ' ' .
            escapeshellarg( $oldTextName ) . ' ' .
            escapeshellarg( $yourTextName );
//          FIXME: In MediaWiki code it uses wfEscapeShellArg, which includes
//          some special code for Windows
//          wfEscapeShellArg( $myTextName ) . ' ' .
//          wfEscapeShellArg( $oldTextName ) . ' ' .
//          wfEscapeShellArg( $yourTextName );
        $handle = popen( $cmd, 'r' );

        if ( fgets( $handle, 1024 ) ) {
            $conflict = true;
        } else {
            $conflict = false;
        }
        pclose( $handle );

        # Merge differences
        $cmd = $diff3 . ' -a -e --merge ' .
            escapeshellarg( $myTextName ) . ' ' .
            escapeshellarg( $oldTextName ) . ' ' .
            escapeshellarg( $yourTextName );
//          FIXME: In MediaWiki code it uses wfEscapeShellArg, which includes
//          some special code for Windows
//          wfEscapeShellArg( $myTextName, $oldTextName, $yourTextName );
        $handle = popen( $cmd, 'r' );

        do {
            $data = fread( $handle, 8192 );
            if ( strlen( $data ) == 0 ) {
                break;
            }
            $result .= $data;
        } while ( true );
        pclose( $handle );
        unlink( $myTextName ); unlink( $oldTextName ); unlink( $yourTextName );

        if ( $result === '' && $old !== '' && $conflict == false ) {
            msg( "Unexpected null result from diff3. Command: $cmd\n", -1 );
            $conflict = true;
        }
        return !$conflict;
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
