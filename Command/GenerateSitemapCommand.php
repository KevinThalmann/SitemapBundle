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
            ->setName('sitemap:generate')
            ->setDescription('Generates the sitemap')
            ->addOption(
                'configuration',
                null,
                InputOption::VALUE_OPTIONAL,
                'Use this, if your configuration file is not at default location',
                'app/Resources/sitemap/paths.yml'
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
        $this->em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $this->router = $this->getContainer()->get('router');

        $configPath = $this->getConfigPath($input->getOption('configuration'));

        $yaml = new Parser();

        try {
            $fileContents = $yaml->parse(file_get_contents($configPath));
        } catch (ParseException $e) {
            printf("Unable to parse the YAML string: %s", $e->getMessage());
        }

        $urls = $this->getUrls($fileContents, $output);

        // Get path to output xml
        if (!isset($fileContents['output'])) {
            $outputPath = $this->getContainer()->get('kernel')->getRootDir() . '/../web/xml/sitemap.xml';
        } else {
            $outputPath = $fileContents['output'];
        }

        $this->render($urls, $outputPath);
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
                throw new \Exception('route parameter "_locale" must be defined when alt_lang are given');
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
     * @param $outputPath
     */
    private function render(array $urls, $outputPath)
    {
        $xml = $this->getContainer()->get('templating')->render('@OngoingSitemap/sitemap.html.twig', array(
            'urls' => $urls,
        ));

        $outputFile = fopen($outputPath, "w+");

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
     */
    private function getConfigPath($relPath)
    {
        $configPath = $this->getContainer()->get('kernel')->getRootDir() . "/../" . $relPath;

        if (!file_exists($configPath)) {
            throw new FileNotFoundException($configPath);
        }

        return $configPath;
    }
} 