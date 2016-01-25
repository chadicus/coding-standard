<?php
/**
 * Generates a warning if else or elseif control structures are used.
 */
final class Chadicus_Sniffs_ControlStructures_ElseIfAndElseDeclarationSniff implements PHP_CodeSniffer_Sniff
{

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_ELSEIF, T_ELSE];
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param integer              $stackPtr  The position of the current token in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $error = 'Use of ELSE and ELSEIF is discouraged. An if expression with an else branch is never necessary. You '
               . 'can rewrite the conditions in a way that the else is not necessary and the code becomes simpler to '
               . 'read.';
        $phpcsFile->addWarning($error, $stackPtr, 'Discouraged');
    }
}
