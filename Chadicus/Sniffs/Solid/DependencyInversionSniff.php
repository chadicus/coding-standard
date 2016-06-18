<?php
/**
 * Issues a warning if the new keyword is found within a class constructor.
 */
final class Chadicus_Sniffs_Solid_DependencyInversionSniff implements PHP_CodeSniffer_Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_FUNCTION];
    }

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer_File $phpcsFile All the tokens found in the document.
     * @param integer              $stackPtr  The position of the current token in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $methodName = $phpcsFile->getDeclarationName($stackPtr);
        if ($methodName !== '__construct') {
            //Ingore usage within other methods
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $scopeStart   = $tokens[$stackPtr]['scope_opener'];
        $newUsage = $phpcsFile->findNext([T_NEW], ($scopeStart + 1), $tokens[$stackPtr]['scope_closer'], true);
        if ($newUsage === false) {
            return;
        }

        $error = 'Use of NEW within a constructor can be a sign of tight coupling. Consider injecting this dependency.';
        $phpcsFile->addWarning($error, $newUsage, 'Discouraged');
    }
}
