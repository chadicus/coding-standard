<?php

namespace Chadicus\Sniffs\ConstrolStructures;

use PHP_CodeSniffer\Sniffs\AbstractScopeSniff;
use PHP_CodeSniffer\Files\File;

/**
 * Generates a warning if else or elseif control structures are used.
 */
final class ElseIfAndElseDeclarationSniff extends AbstractScopeSniff
{
    /**
     * Constructs the test with the tokens it wishes to listen for.
     */
    public function __construct()
    {
        parent::__construct([T_CLASS], [T_ELSEIF, T_ELSE]);
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     * @param int                         $currScope A pointer to the start of the scope.
     *
     * @return void
     */
    public function processTokenWithinScope(File $phpcsFile, $stackPtr, $currScope)
    {
        $error = 'Use of ELSE and ELSEIF is discouraged. An if expression with an else branch is never necessary. You '
               . 'can rewrite the conditions in a way that the else is not necessary and the code becomes simpler to '
               . 'read.';
        $phpcsFile->addWarning($error, $stackPtr, 'Discouraged');
    }

    /**
     * Process of tokens outside of scope.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The current file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     * @return void
     */
    protected function processTokenOutsideScope(File $phpcsFile, $stackPtr)
    {
    }
}
