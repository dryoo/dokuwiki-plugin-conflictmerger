<?php
/**
 * Unit tests for Conflict Merger Plugin for Dokuwiki
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Daniel Calviño Sánchez <danxuliu@gmail.com>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../').'/');
require_once(DOKU_INC.'lib/plugins/conflictmerger/action.php');

require_once(DOKU_INC.'inc/utf8.php');

//TODO Necessary to be run from the browser, but... is this the proper way to do it?
global $conf;
if (!isset($conf['datadir'])) $conf['datadir'] = DOKU_INC . $conf['savedir'].'/pages';

class Merge_test extends UnitTestCase {

    var $id = 'testmerge';
    var $action;

    function setUp() {
        $this->action = new action_plugin_conflictmerger();
    }

    function getBasename() {
        return wikiFN($this->id);
    }

    function testMerge() {
        $old = "First paragraph\n\nSecond paragraph\n\nThird paragraph";
        $mine = "First paragraph modified by user 1\n\nSecond paragraph\n\nThird paragraph";
        $yours = "First paragraph\n\nSecond paragraph modified by user 2\n\nThird paragraph";
        $result;

        $this->assertTrue($this->action->merge($this->id, $old, $mine, $yours, $result));
        $this->assertEqual("First paragraph modified by user 1\n\nSecond paragraph modified by user 2\n\nThird paragraph", $result);

        $this->assertFalse(file_exists($this->getBasename() . '-merge-mine'));
        $this->assertFalse(file_exists($this->getBasename() . '-merge-old'));
        $this->assertFalse(file_exists($this->getBasename() . '-merge-your'));
    }

    function testMergeConflictSingleLine() {
        $old = "A bunch of words in a single line";
        $mine = "A bunch of words edited by user 1 in a single line";
        $yours = "A bunch of words in a single line edited by user 2";
        $result = '';

        $this->assertFalse($this->action->merge($this->id, $old, $mine, $yours, $result));
        $this->assertNotEqual('', $result);

        $this->assertFalse(file_exists($this->getBasename() . '-merge-mine'));
        $this->assertFalse(file_exists($this->getBasename() . '-merge-old'));
        $this->assertFalse(file_exists($this->getBasename() . '-merge-your'));
    }

    function testMergeConflict() {
        $old = "First paragraph\n\nSecond paragraph\n\nThird paragraph";
        $mine = "First paragraph modified by user 1\n\nSecond paragraph\n\nThird paragraph";
        $yours = "First paragraph modified by user 2\n\nSecond paragraph\n\nThird paragraph";
        $result = '';

        $this->assertFalse($this->action->merge($this->id, $old, $mine, $yours, $result));
        $this->assertNotEqual('', $result);

        $this->assertFalse(file_exists($this->getBasename() . '-merge-mine'));
        $this->assertFalse(file_exists($this->getBasename() . '-merge-old'));
        $this->assertFalse(file_exists($this->getBasename() . '-merge-your'));
    }

    function testMergeFailureEmptyDiff3() {
        global $conf;
        $temporalDiff3 = $conf['plugin']['conflictmerger']['diff3'];
        $conf['plugin']['conflictmerger']['diff3'] = '';

        $old = "First paragraph\n\nSecond paragraph\n\nThird paragraph";
        $mine = "First paragraph modified by user 1\n\nSecond paragraph\n\nThird paragraph";
        $yours = "First paragraph\n\nSecond paragraph modified by user 2\n\nThird paragraph";
        $result = "Previous value";

        $this->assertFalse($this->action->merge($this->id, $old, $mine, $yours, $result));
        $this->assertEqual('', $result);

        $this->assertFalse(file_exists($this->getBasename() . '-merge-mine'));
        $this->assertFalse(file_exists($this->getBasename() . '-merge-old'));
        $this->assertFalse(file_exists($this->getBasename() . '-merge-your'));

        $conf['plugin']['conflictmerger']['diff3'] = $temporalDiff3;
    }

    function testMergeFailureWrongDiff3Path() {
        global $conf;
        $temporalDiff3 = $conf['plugin']['conflictmerger']['diff3'];
        $conf['plugin']['conflictmerger']['diff3'] = '/a/path/where/you/are/not/likely/to/have/diff3';

        $old = "First paragraph\n\nSecond paragraph\n\nThird paragraph";
        $mine = "First paragraph modified by user 1\n\nSecond paragraph\n\nThird paragraph";
        $yours = "First paragraph\n\nSecond paragraph modified by user 2\n\nThird paragraph";
        $result = "Previous value";

        $this->assertFalse($this->action->merge($this->id, $old, $mine, $yours, $result));
        $this->assertEqual('', $result);

        $this->assertFalse(file_exists($this->getBasename() . '-merge-mine'));
        $this->assertFalse(file_exists($this->getBasename() . '-merge-old'));
        $this->assertFalse(file_exists($this->getBasename() . '-merge-your'));

        $conf['plugin']['conflictmerger']['diff3'] = $temporalDiff3;
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
