<?php

/**
 * Dreamscapes/Ldap-Core
 *
 * Licensed under the BSD (3-Clause) license
 * For full copyright and license information, please see the LICENSE file
 *
 * @author      Tomasz Sawicki <falundir@gmail.com>
 * @copyright   2015 Tomasz Sawicki
 * @link        https://github.com/Dreamscapes/Ldap-Core
 * @license     http://choosealicense.com/licenses/bsd-3-clause   BSD (3-Clause) License
 */

namespace Dreamscapes\Ldap\Core;

/**
 * Helper for paged search results
 *
 * @see Ldap::ldapSearchPaged()
 *
 * @package Ldap-Core
 */
class PagedResult implements \Iterator
{

    /**
     * @var Ldap ldap encapsulation object
     */
    protected $ldap;

    /**
     * @var string the base DN for the directory search
     * @see http://php.net/manual/en/function.ldap-search.php
     */
    protected $baseDn;

    /**
     * @var string ldap query filter
     * @see http://php.net/manual/en/function.ldap-search.php
     */
    protected $filter;

    /**
     * @var array array of the required attributes, empty array means all attributes
     * @see http://php.net/manual/en/function.ldap-search.php
     */
    protected $attributes;

    /**
     * @var int search scope, one of Ldap::SCOPE_SUBTREE or Ldap::SCOPE_ONELEVEL
     * @see http://php.net/manual/en/function.ldap-search.php
     */
    protected $scope;

    /**
     * @var bool should be set to true if only attribute types are wanted
     * @see http://php.net/manual/en/function.ldap-search.php
     */
    protected $attrsOnly;

    /**
     * @var int page size
     */
    protected $pageSize;

    /**
     * @var int number of seconds how long is spend on the search, 0 means no limit
     * @see http://php.net/manual/en/function.ldap-search.php
     */
    protected $timeLimit;

    /**
     * @var int specifies how aliases should be handled during the search
     * @see http://php.net/manual/en/function.ldap-search.php
     */
    protected $deref;

    /**
     * @see http://php.net/manual/en/function.ldap-control-paged-result-response.php
     * @var string page cookie (an opaque structure sent by the server)
     */
    public $cookie;

    /**
     * @see http://php.net/manual/en/function.ldap-control-paged-result-response.php
     * @var int the estimated number of entries to retrieve
     */
    public $estimated;

    /**
     * @var int|null current page index (1-based)
     */
    protected $pageIndex;

    /**
     * @var Result|null current page result
     */
    protected $pageResult;

    /**
     * Create a new instance
     *
     * @param  Ldap    $ldap        Ldap encapsulation object.
     * @param  string  $baseDn      The base DN for the directory
     * @param  string  $filter      Ldap query filter (an empty filter is not allowed)
     * @param  array   $attributes  An array of the required attributes, e.g. array("mail", "sn",
     *                              "cn"). Empty array (the default) means all attributes
     * @param  int     $scope       One of self::SCOPE_SUBTREE or self::SCOPE_ONELEVEL
     * @param  boolean $attrsOnly   Should be set to 1 if only attribute types are wanted
     * @param  integer $pageSize    Enables you to limit the count of entries fetched. Setting this
     *                              to 0 means no limit
     * @param  integer $timeLimit   Sets the number of seconds how long is spend on the search.
     *                              Setting this to 0 means no limit.
     * @param  integer $deref       Specifies how aliases should be handled during the search
     * @throws \Exception           On unrecognised/unsupported search scope
     */
    public function __construct(
        Ldap $ldap,
        $baseDn,
        $filter,
        array $attributes = [],
        $scope = Ldap::SCOPE_SUBTREE,
        $attrsOnly = false,
        $pageSize = 1000,
        $timeLimit = 0,
        $deref = LDAP_DEREF_NEVER
    ) {
        if ($scope !== Ldap::SCOPE_ONELEVEL && $scope !== Ldap::SCOPE_SUBTREE) {
            throw new \Exception(sprintf('Unrecognised or unsupported search scope %s', $scope));
        }

        $this->ldap = $ldap;
        $this->baseDn = $baseDn;
        $this->filter = $filter;
        $this->attributes = $attributes;
        $this->scope = $scope;
        $this->attrsOnly = $attrsOnly;
        $this->pageSize = $pageSize;
        $this->timeLimit = $timeLimit;
        $this->deref = $deref;

        if ($this->pageSize < 0) {
            $this->pageSize = 1000;
        }
    }

    /**
     * Returns the next (or first) result page, or null when no more pages are available
     *
     * @param bool $rewind true to get the first page
     * @return Result|null
     * @throws \Exception On paging error
     */
    public function getNextPageResult($rewind = false)
    {
        if ($rewind) {
            $this->rewindPages();
        }

        //no more pages
        if ($this->cookie === '') {
            $this->pageResult = null;
            $this->pageIndex = null;
            return null;
        }

        $pagedResult = $this->ldap->pagedResult($this->pageSize, true, $this->cookie);
        if ($pagedResult === false) {
            throw new \Exception('Error when setting up paging by LDAP server');
        }

        $this->pageResult = $this->ldap->ldapSearch($this->baseDn, $this->filter, $this->attributes, $this->scope,
            $this->attrsOnly, $this->pageSize, $this->timeLimit, $this->deref);

        $pagedResultResponse = $this->pageResult->pagedResultResponse();
        if ($pagedResultResponse === false) {
            throw new \Exception('Error when setting up paging by LDAP server');
        }

        $this->cookie = $pagedResultResponse['cookie'];
        $this->estimated = $pagedResultResponse['estimated'];

        $this->pageIndex++;

        return $this->pageResult;
    }

    /**
     * Resets the current page to initial state
     *
     * @return void
     */
    public function rewindPages()
    {
        $this->pageResult = null;
        $this->pageIndex = null;
        $this->cookie = null;
        $this->estimated = null;
    }

    /**
     * Returns page cookie (an opaque structure sent by the server) returned after current page was fetched
     * or null when no page was fetched yet
     *
     * @return string|null
     */
    public function getCookie()
    {
        return $this->cookie;
    }

    /**
     * Returns the estimated number of entries to retrieve returned after current page was fetched
     * or null when no page was fetched yet
     *
     * @return int|null
     */
    public function getEstimated()
    {
        return $this->estimated;
    }

    /**
     * Returns current page index (1-based) or null when no page was fetched yet
     *
     * @return int|null
     */
    public function getCurrentPageIndex()
    {
        return $this->pageIndex;
    }

    /**
     * Returns current page result or null when no page was fetched yet
     *
     * @return Result|null
     */
    public function getCurrentPageResult()
    {
        return $this->pageResult;
    }

    /**
     * Implementation of Iterator::current()
     *
     * @link http://php.net/manual/en/iterator.current.php
     * @return Result|null current page result on succes, or null on invalid page
     */
    public function current()
    {
        return $this->pageResult;
    }

    /**
     * Implementation of Iterator::next()
     *
     * @link http://php.net/manual/en/iterator.next.php
     * @return void
     */
    public function next()
    {
        $this->getNextPageResult();
    }

    /**
     * Implementation of Iterator::key()
     *
     * @link http://php.net/manual/en/iterator.key.php
     * @return int|null page index on success, or null on invalid page
     */
    public function key()
    {
        return $this->pageIndex;
    }

    /**
     * Implementation of Iterator::valid()
     *
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean true on valid page or false on invalid page
     */
    public function valid()
    {
        return $this->pageResult !== null;
    }

    /**
     * Implementation of Iterator::rewind()
     *
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void
     */
    public function rewind()
    {
        $this->getNextPageResult(true);
    }
}
