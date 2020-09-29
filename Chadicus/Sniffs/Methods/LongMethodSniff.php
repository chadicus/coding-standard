<?php

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Issues warning with method length greater than 100 and issues error if length is greater than 200.
 */
final class Chadicus_Sniffs_Methods_LongMethodSniff implements Sniff
{
    /**
     * Length at which a warning will be triggered.
     *
     * @var integer
     */
    public $maxLength = 50;

    /**
     * Length at which an error will be triggered
     *
     * @var integer
     */
    public $absoluteMaxLength = 100;

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return array(T_FUNCTION);
    }

    /**
     * @param File $phpcsFile The file where the token was found.
     * @param intege               $stackPtr  The position in the stack where the token was found.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $token = $tokens[$stackPtr];
        $length = 0;
        if (isset($token['scope_opener']) === false) {
            //ignore empty methods
            return;
        }

        //get the full length of the method
        $firstToken = $tokens[$token['scope_opener']];
        $lastToken = $tokens[$token['scope_closer']];
        $length = $lastToken['line'] - $firstToken['line'];

        if ($length > $this->absoluteMaxLength) {
            $error = sprintf("Method's length (%d) exceeds maximum length of %d", $length, $this->absoluteMaxLength);
            $phpcsFile->addError($error, $stackPtr, 'MethodTooBig');
            return;
        }

        if ($length > $this->maxLength) {
            $error = sprintf("Method's length (%d) exceeds %d, consider refactoring", $length, $this->maxLength);
            $phpcsFile->addWarning($error, $stackPtr, 'MethodTooBig');
        }
    }
}
