<?php
/**
 * Acceptance tests for Conflict Merger Plugin for Dokuwiki
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Daniel Calviño Sánchez <danxuliu@gmail.com>
 */

//TODO apply PageObjects pattern (http://code.google.com/p/webdriver/wiki/PageObjects)?
class Acceptance_test extends WebTestCase {

    //Button labels
    var $editButton = 'Edit this page';
    var $saveButton = 'Save';
    var $cancelButton = 'Cancel';

    //Text area and hidden input name
    var $textField = 'wikitext';

    //URL to the page used in the tests, including trailing & to add further parameters
    var $basePageName;

    //Browser that acts as a second user editing the pages
    var $secondUser;

    function setUp() {
        $this->basePageName = WEB_TEST_URL . '/doku.php?id=testConflictMerger&';

        $this->secondUser = &$this->createBrowser();

        $this->get($this->basePageName . 'do=edit');
        $this->setField($this->textField, "First line\n\nSecond line\n\nThird line");
        $this->click($this->saveButton);
    }

    function tearDown() {
        unset($this->secondUser);

        $this->get($this->basePageName . 'do=edit');
        $this->setField($this->textField, '');
        $this->click($this->saveButton);
    }

    function assertViewPage($text) {
        //assertText ignores new lines
        $text = preg_replace("/\n+/", ' ', $text);

        $this->assertNoText('merged');
        $this->assertText($text);
    }

    function assertRightMergePage($textField) {
        //assertFieldByName seems to use \r\n line ending
        $textField = str_replace("\n", "\r\n", $textField);

        $this->assertText('could be merged');
        $this->assertFieldByName($this->textField, $textField);

        //Diff table isn't checked as it is too complex
    }

    function assertWrongMergePage($textField) {
        //assertFieldByName seems to use \r\n line ending
        $textField = str_replace("\n", "\r\n", $textField);

        $this->assertText('couldn\'t be merged');
        $this->assertFieldByName($this->textField, $textField);

        //Diff table isn't checked as it is too complex
    }

    function assertDiffEditPage($textField) {
        //assertFieldByName seems to use \r\n line ending
        $textField = str_replace("\n", "\r\n", $textField);

        $this->assertText('Edit the page and hit Save');
        $this->assertFieldByName($this->textField, $textField);
    }

    function testEditCancel() {
        $this->get($this->basePageName);
        $this->click($this->editButton);

        $this->setField($this->textField, 'Some text');

        $this->assertTrue($this->click($this->cancelButton));

        $this->assertViewPage("First line\n\nSecond line\n\nThird line");
    }

    function testEditSave() {
        $this->get($this->basePageName);
        $this->click($this->editButton);

        $this->setField($this->textField, 'Some text');

        sleep(1);
        $this->assertTrue($this->click($this->saveButton));

        $this->assertViewPage('Some text');
    }

    function testEditSaveMergeable() {
        $this->get($this->basePageName);
        $this->click($this->editButton);

        $this->setField($this->textField, "First line modified by user 1\n\nSecond line\n\nThird line");

        $this->secondUser->get($this->basePageName . 'do=edit');
        $this->secondUser->setField($this->textField, "First line\n\nSecond line\n\nThird line modified by user 2");
        sleep(1);
        $this->secondUser->click($this->saveButton);

        $this->assertTrue($this->click($this->saveButton));

        $this->assertRightMergePage("First line modified by user 1\n\nSecond line\n\nThird line modified by user 2");
    }

    function testEditSaveNotMergeable() {
        $this->get($this->basePageName);
        $this->click($this->editButton);

        $this->setField($this->textField, "First line modified by user 1\n\nSecond line\n\nThird line");

        $this->secondUser->get($this->basePageName . 'do=edit');
        $this->secondUser->setField($this->textField, "First line modified by user 2\n\nSecond line\n\nThird line modified by user 2");
        sleep(1);
        $this->secondUser->click($this->saveButton);

        $this->assertTrue($this->click($this->saveButton));

        $this->assertWrongMergePage("First line modified by user 1\n\nSecond line\n\nThird line");
    }

    function prepareRightMergePage() {
        $this->get($this->basePageName);
        $this->click($this->editButton);

        $this->setField($this->textField, "First line modified by user 1\n\nSecond line\n\nThird line");

        $this->secondUser->get($this->basePageName . 'do=edit');
        $this->secondUser->setField($this->textField, "First line\n\nSecond line\n\nThird line modified by user 2");
        sleep(1);
        $this->secondUser->click($this->saveButton);

        $this->click($this->saveButton);
    }

    function testRightMergeCancel() {
        $this->prepareRightMergePage();

        $this->assertTrue($this->click($this->cancelButton));

        $this->assertViewPage("First line\n\nSecond line\n\nThird line modified by user 2");
    }

    function testRightMergeEdit() {
        $this->prepareRightMergePage();

        $this->assertTrue($this->click($this->editButton));

        $this->assertDiffEditPage("First line modified by user 1\n\nSecond line\n\nThird line modified by user 2");
    }

    function testRightMergeEditNewSaveMergeable() {
        $this->prepareRightMergePage();

        $this->click($this->editButton);

        $this->setField($this->textField, "First line modified by user 1 again\n\nSecond line\n\nThird line modified by user 2");
        sleep(1);
        $this->assertTrue($this->click($this->saveButton));

        $this->assertViewPage("First line modified by user 1 again\n\nSecond line\n\nThird line modified by user 2");
    }

    function testRightMergeEditNewSaveNotMergeable() {
        $this->prepareRightMergePage();

        $this->click($this->editButton);

        $this->setField($this->textField, "First line modified by user 1\n\nSecond line\n\nThird line modified by user 1");
        sleep(1);
        $this->assertTrue($this->click($this->saveButton));

        $this->assertViewPage("First line modified by user 1\n\nSecond line\n\nThird line modified by user 1");
    }

    function testRightMergeEditMergeable() {
        $this->prepareRightMergePage();

        $this->secondUser->get($this->basePageName . 'do=edit');
        $this->secondUser->setField($this->textField, "First line\n\nSecond line modified by user 3\n\nThird line");
        sleep(1);
        $this->secondUser->click($this->saveButton);

        $this->assertTrue($this->click($this->editButton));

        $this->assertRightMergePage("First line modified by user 1\n\nSecond line modified by user 3\n\nThird line");
    }

    function testRightMergeEditNotMergeable() {
        $this->prepareRightMergePage();

        $this->secondUser->get($this->basePageName . 'do=edit');
        $this->secondUser->setField($this->textField, "First line modified by user 3\n\nSecond line\n\nThird line");
        sleep(1);
        $this->secondUser->click($this->saveButton);

        $this->assertTrue($this->click($this->editButton));

        $this->assertWrongMergePage("First line modified by user 1\n\nSecond line\n\nThird line modified by user 2");
    }

    function testRightMergeSave() {
        $this->prepareRightMergePage();

        sleep(1);
        $this->assertTrue($this->click($this->saveButton));

        $this->assertViewPage("First line modified by user 1\n\nSecond line\n\nThird line modified by user 2");
    }

    function testRightMergeSaveMergeable() {
        $this->prepareRightMergePage();

        $this->secondUser->get($this->basePageName . 'do=edit');
        $this->secondUser->setField($this->textField, "First line\n\nSecond line\n\nThird line modified by user 3");
        sleep(1);
        $this->secondUser->click($this->saveButton);

        $this->assertTrue($this->click($this->saveButton));

        $this->assertRightMergePage("First line modified by user 1\n\nSecond line\n\nThird line modified by user 3");
    }

    function testRightMergeSaveNotMergeable() {
        $this->prepareRightMergePage();

        $this->secondUser->get($this->basePageName . 'do=edit');
        $this->secondUser->setField($this->textField, "First line modified by user 3\n\nSecond line\n\nThird line modified by user 2");
        sleep(1);
        $this->secondUser->click($this->saveButton);

        $this->assertTrue($this->click($this->saveButton));

        $this->assertWrongMergePage("First line modified by user 1\n\nSecond line\n\nThird line modified by user 2");
    }

    function prepareWrongMergePage() {
        $this->get($this->basePageName);
        $this->click($this->editButton);

        $this->setField($this->textField, "First line modified by user 1\n\nSecond line\n\nThird line");

        $this->secondUser->get($this->basePageName . 'do=edit');
        $this->secondUser->setField($this->textField, "First line modified by user 2\n\nSecond line\n\nThird line");
        sleep(1);
        $this->secondUser->click($this->saveButton);

        $this->click($this->saveButton);
    }

    function testWrongMergeCancel() {
        $this->prepareWrongMergePage();

        $this->assertTrue($this->click($this->cancelButton));

        $this->assertViewPage("First line modified by user 2\n\nSecond line\n\nThird line");
    }

    function testWrongMergeEdit() {
        $this->prepareWrongMergePage();

        $this->assertTrue($this->click($this->editButton));

        $this->assertDiffEditPage("First line modified by user 1\n\nSecond line\n\nThird line");
    }

    function testWrongMergeEditNewSaveMergeable() {
        $this->prepareWrongMergePage();

        $this->click($this->editButton);

        $this->setField($this->textField, "First line modified by user 2\n\nSecond line\n\nThird line modified by user 1");
        sleep(1);
        $this->assertTrue($this->click($this->saveButton));

        $this->assertRightMergePage("First line modified by user 2\n\nSecond line\n\nThird line modified by user 1");
    }

    function testWrongMergeEditNewSaveNotMergeable() {
        $this->prepareWrongMergePage();

        $this->click($this->editButton);

        $this->setField($this->textField, "First line modified by user 1\n\nSecond line\n\nThird line modified by user 1");
        sleep(1);
        $this->assertTrue($this->click($this->saveButton));

        $this->assertWrongMergePage("First line modified by user 1\n\nSecond line\n\nThird line modified by user 1");
    }

    function testWrongMergeEditMergeable() {
        $this->prepareWrongMergePage();

        $this->secondUser->get($this->basePageName . 'do=edit');
        $this->secondUser->setField($this->textField, "First line\n\nSecond line modified by user 3\n\nThird line");
        sleep(1);
        $this->secondUser->click($this->saveButton);

        $this->assertTrue($this->click($this->editButton));

        $this->assertRightMergePage("First line modified by user 1\n\nSecond line modified by user 3\n\nThird line");
    }

    function testWrongMergeEditNotMergeable() {
        $this->prepareWrongMergePage();

        $this->secondUser->get($this->basePageName . 'do=edit');
        $this->secondUser->setField($this->textField, "First line modified by user 3\n\nSecond line\n\nThird line");
        sleep(1);
        $this->secondUser->click($this->saveButton);

        $this->assertTrue($this->click($this->editButton));

        $this->assertWrongMergePage("First line modified by user 1\n\nSecond line\n\nThird line");
    }

    function testWrongMergeSave() {
        $this->prepareWrongMergePage();

        sleep(1);
        $this->assertTrue($this->click($this->saveButton));

        $this->assertViewPage("First line modified by user 1\n\nSecond line\n\nThird line");
    }

    function testWrongMergeSaveMergeable() {
        $this->prepareWrongMergePage();

        $this->secondUser->get($this->basePageName . 'do=edit');
        $this->secondUser->setField($this->textField, "First line\n\nSecond line\n\nThird line modified by user 3");
        sleep(1);
        $this->secondUser->click($this->saveButton);

        $this->assertTrue($this->click($this->saveButton));

        $this->assertRightMergePage("First line modified by user 1\n\nSecond line\n\nThird line modified by user 3");
    }

    function testWrongMergeSaveNotMergeable() {
        $this->prepareWrongMergePage();

        $this->secondUser->get($this->basePageName . 'do=edit');
        $this->secondUser->setField($this->textField, "First line modified by user 3\n\nSecond line\n\nThird line modified by user 3");
        sleep(1);
        $this->secondUser->click($this->saveButton);

        $this->assertTrue($this->click($this->saveButton));

        $this->assertWrongMergePage("First line modified by user 1\n\nSecond line\n\nThird line");
    }

    //FIXME Tests for failed merges when diff3 isn't found, how to do it?
}

?>
