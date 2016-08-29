<?php

use SilverStripe\Dev\SapphireTest;

class TestSessionStubCodeWriterTest extends SapphireTest
{

    public function tearDown()
    {
        parent::tearDown();

        $file = TEMP_FOLDER . '/TestSessionStubCodeWriterTest-file.php';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public function testWritesHeaderOnNewFile()
    {
        $file = TEMP_FOLDER . '/TestSessionStubCodeWriterTest-file.php';
        $writer = new TestSessionStubCodeWriter($file);
        $writer->write('foo();', false);
        $this->assertFileExists($file);
        $this->assertEquals(
            file_get_contents($writer->getFilePath()),
            "<?php\nfoo();\n"
        );
    }

    public function testWritesWithAppendOnExistingFile()
    {
        $file = TEMP_FOLDER . '/TestSessionStubCodeWriterTest-file.php';
        $writer = new TestSessionStubCodeWriter($file);
        $writer->write('foo();', false);
        $writer->write('bar();', false);
        $this->assertFileExists($file);
        $this->assertEquals(
            file_get_contents($writer->getFilePath()),
            "<?php\nfoo();\nbar();\n"
        );
    }

    public function testReset()
    {
        $file = TEMP_FOLDER . '/TestSessionStubCodeWriterTest-file.php';
        $writer = new TestSessionStubCodeWriter($file);
        $writer->write('foo();', false);
        $this->assertFileExists($file);
        $writer->reset();
        $this->assertFileNotExists($file);
    }
}
