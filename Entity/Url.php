<?php
/**
 * Created by PhpStorm.
 * User: kevinthalmann
 * Date: 05.12.14
 * Time: 10:56
 */

namespace Ongoing\SitemapBundle\Entity;


class Url
{
    /**
     * @var string
     */
    private $route;

    /**
     * @var array
     */
    private $routeParams;

    /**
     * @var \DateTime
     */
    private $lastmod;

    /**
     * @var string
     */
    private $changefreq;

    /**
     * @var float
     */
    private $priority;

    /**
     * @var array
     */
    private $alternativeLang;


    function __construct($route, array $routeParams, $options)
    {
        $this->route = $route;
        $this->routeParams = $routeParams;
        $this->changefreq = (isset($options['change_freq'])) ? $options['change_freq'] : 'never';
        $this->priority = (isset($options['priority'])) ? $options['priority'] : 0.1;
        $this->alternativeLang = (isset($options['alt_lang'])) ? $options['alt_lang'] : array();
    }


    /**
     * @return string
     */
    public function getChangefreq()
    {
        return $this->changefreq;
    }

    /**
     * @param string $changefreq
     */
    public function setChangefreq($changefreq)
    {
        $this->changefreq = $changefreq;
    }

    /**
     * @return \DateTime
     */
    public function getLastmod()
    {
        return $this->lastmod;
    }

    /**
     * @param \DateTime $lastmod
     */
    public function setLastmod($lastmod)
    {
        $this->lastmod = $lastmod;
    }

    /**
     * @return float
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param float $priority
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;
    }

    /**
     * @return array
     */
    public function getAlternativeLang()
    {
        return $this->alternativeLang;
    }

    /**
     * @param array $alternativeLang
     */
    public function setAlternativeLang($alternativeLang)
    {
        $this->alternativeLang = $alternativeLang;
    }

    /**
     * @return string
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * @param string $route
     */
    public function setRoute($route)
    {
        $this->route = $route;
    }

    /**
     * @return array
     */
    public function getRouteParams()
    {
        return $this->routeParams;
    }

    /**
     * @param array $routeParams
     */
    public function setRouteParams($routeParams)
    {
        $this->routeParams = $routeParams;
    }


} 