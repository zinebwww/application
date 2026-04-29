<?php
use PHPUnit\Framework\TestCase;

class AbsenceTest extends TestCase {
    public function testAddition() {
        $this->assertEquals(2, 1 + 1);
    }

    public function testSrcFolderExists() {
        $this->assertDirectoryExists('src');
    }
}
