<?php
/**
 * Created by PhpStorm.
 * User: kevinthalmann
 * Date: 05.12.14
 * Time: 09:29
 */

namespace Ongoing\SitemapBundle\Command;


use Doctrine\ORM\EntityManager;
use Ongoing\SitemapBundle\Entity\Url;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\Routing\Router;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

class GenerateSitemapCommand extends ContainerAwareCommand
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var Router
     */
    private $router;

    protected function configure()
    {
        $this
            ->setName('og:sitemap:generate')
            ->setDescription('Generates a sitemap with the configuration provided')
            ->addArgument(
                'host',
                InputArgument::REQUIRED,
                'Host (eg. ongoing.ch)'
            )
            ->addOption(
                'configuration',
                null,
                InputOption::VALUE_OPTIONAL,
                'Use this, if your configuration file is not at default location',
                'app/Resources/sitemap/paths.yml'
            )
            ->addOption(
                'mandateId',
                null,
                InputOption::VALUE_OPTIONAL,
                'Needs to be set to select correct insertions'
            )
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->router = $this->getContainer()->get('router');

        $context = $this->router->getContext();
        $context->setHost($input->getArgument('host'));
        $context->setScheme('http');

        if ($mandateId = $input->getOption('mandateId')) {
            $this->em = $this->getEntityManagerByMandate((int)$mandateId);
        } else {
            $this->em = $this->getContainer()->get('doctrine.orm.entity_manager');
        }

        $this->router = $this->getContainer()->get('router');

        $configPath = $this->getConfigPath($input->getOption('configuration'));
        $configuration = $this->parse($configPath);

        $urls = $this->getUrls($configuration, $output);

        // Get path to output xml
        if (!isset($configuration['output'])) {
            $outputDir = $this->getContainer()->get('kernel')->getRootDir() . '/../web/xml/';
        } else {
            $outputDir = $configuration['output'];
        }

        $this->render($urls, $outputDir);
    }

    /**
     * Parse the configuration for the routes
     *
     * @param $configPath
     * @return mixed
     */
    private function parse($configPath)
    {
        $yaml = new Parser();

        try {
            $fileContents = $yaml->parse(file_get_contents($configPath));
        } catch (ParseException $e) {
            printf("Unable to parse the YAML string: %s", $e->getMessage());
        }

        return $fileContents;
    }

    /**
     * @param $fileContents
     * @param OutputInterface $output
     * @return array
     * @throws \Exception
     */
    private function getUrls($fileContents, OutputInterface $output)
    {
        $urls = array();
        foreach ($fileContents['routes'] as $routename => $options) {
            $output->writeln('Generating Urls for Route: ' . $routename);

            if (!isset($options['route_params'])) {
                // No route parameters set
                $urls = array_merge($urls, $this->createUrl($routename, array(), $options));
            } else {

                $tmpRouteParams = array();
                $multiRoutes = false;
                foreach ($options['route_params'] as $paramName => $paramValue) {

                    if (is_array($paramValue)) {
                        // Multiple urls are beeing generated
                        $multiRoutes = true;
                        if (isset($paramValue['fetch'])) {
                            // Get entity properties from repository to generate multiple urls
                            $placeholderValues = $this->getPlaceholderValues($paramValue['fetch']['repository'], $paramValue['fetch']['property']);
                        } elseif (isset($paramValue['values'])) {
                            // Possible placeholder values are provided manually
                            $placeholderValues = $paramValue['values'];
                        } else {
                            throw new \Exception('Unrecognized value for parameter: "' . $paramName . '". Options are "fetch" or "values"');
                        }

                        $tmpRouteParams[$paramName] = $placeholderValues;
                    } else {
                        $tmpRouteParams[$paramName] = $paramValue;
                    }
                }

                if ($multiRoutes) {
                    foreach ($tmpRouteParams as $routeParamName => $routeParamValue) {
                        if (is_array($routeParamValue)) {
                            foreach ($routeParamValue as $routeParamValueOption) {

                                $routeParams = $tmpRouteParams;
                                $routeParams[$routeParamName] = $routeParamValueOption;

                                $urls = array_merge($urls, $this->createUrl($routename, $routeParams, $options));
                            }
                        }
                    }
                } else {
                    $urls = array_merge($urls, $this->createUrl($routename, $tmpRouteParams, $options));
                }
            }
        }

        return $urls;
    }

    /**
     * @param $routename
     * @param array $routeParams
     * @param array $options
     * @return array
     * @throws \Exception
     */
    private function createUrl($routename, array $routeParams, array $options)
    {
        $urls = array();

        $urls[] = new Url($routename, $routeParams, $options);

        // Generate own Urls for alternative languages
        if (isset($options['alt_lang'])) {

            if (!isset($routeParams['_locale'])) {
                throw new \Exception('route parameter "_locale" must be defined when alt_lang are defined');
            }

            foreach ($options['alt_lang'] as $lang) {

                $newAltLang = $options['alt_lang'];
                $tmp = array_search($lang, $newAltLang);
                $newAltLang[$tmp] = $routeParams['_locale'];
                $newOptions = array_merge($options, array('alt_lang' => $newAltLang));

                $newRouteParams = array_merge($routeParams, array('_locale' => $lang));

                $urls[] = new Url($routename, $newRouteParams, $newOptions);
            }
        }

        return $urls;
    }

    /**
     * @param array $urls
     * @param $outputDir
     */
    private function render(array $urls, $outputDir)
    {
        $xml = $this->getContainer()->get('templating')->render('@OngoingSitemap/sitemap.html.twig', array(
            'urls' => $urls,
        ));

        // create output folder if doesnt exist
        if (!file_exists($outputDir)) {
            mkdir($outputDir);
        }

        $outputFile = fopen($outputDir . 'sitemap.xml', "w+");

        fwrite($outputFile, $xml);
    }

    /**
     * @param $entityRepository
     * @param $property
     * @return array
     */
    private function getPlaceholderValues($entityRepository, $property)
    {
        $repo = $this->em->getRepository($entityRepository);

        $qb = $repo->createQueryBuilder('xy');

        $qb
            ->select('xy.' . $property);

        $result = $qb->getQuery()->getResult();

        return array_map('current', $result);
    }

    /**
     * Return absolute path and check for existance
     *
     * @param $relPath
     * @return string
     * @throws FileNotFoundException
     */
    private function getConfigPath($relPath)
    {
        $configPath = $this->getContainer()->get('kernel')->getRootDir() . "/../" . $relPath;

        if (!file_exists($configPath)) {
            throw new FileNotFoundException($configPath);
        }

        return $configPath;
    }

    /**
     * @param $mandateId
     * @return \Doctrine\ORM\EntityManager
     * @throws \Exception
     */
    private function getEntityManagerByMandate($mandateId)
    {
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $filter = $em->getFilters()->enable("multitenancy_filter");
        $mandate = $em->getRepository("OMSPortalBundle:Website")->find($mandateId);
        if (!$mandate) {
            throw new \Exception("Unable to fetch website");
        }
        $filter->setParameter('mtm', $mandate->getId());

        return $em;
    }
} 