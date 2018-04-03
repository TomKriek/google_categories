<?php

namespace TomKriek\GoogleCategories;

class Sorter
{

    /**
     * @var array containing all categories during pre-sorting and the final sorting result
     */
    private $categories = [];

    /**
     * @var int $notAdded keep track of categories that did not get added for some reason
     */
    private $notAdded = 0;

    /**
     * @var int $ignored keep track of the amount of lines that have been ignored from the files
     */
    private $ignored = 0;

    /**
     * @var int $added counter to keep track of the amount of categories that have been reorganized
     */
    private $added = 0;

    /**
     * @var int $total total amount of categories to reorganize
     */
    private $total = 0;

    /**
     * @var array|mixed $locales array containing the locales of the files to retrieve and process
     */
    private $locales;

    /**
     * @var bool $debug variable to determine if we should output anything to the browser of CLI
     */
    private $debug;

    /**
     * Sorter constructor.
     *
     * @param array $isos list of iso locales to process
     * @param bool $debug toggle debugging info during sorting and reorganizing
     */
    public function __construct(array $isos, $debug = false)
    {
        if (\count($isos) === 0) {
            // Include our list of isos if none have been passed along
            $isos = require __DIR__ . '/isos.php';
        }

        $this->debug = $debug;

        $this->locales = $isos;
    }

    /**
     * Function to reorganize our presorted categories in a proper list
     *
     * Our sorted list will contain a key for each translation, a parent_id and their own category_id as index
     *
     * @return Sorter $this
     */
    public function sort(): Sorter
    {
        if (\count($this->categories) === 0) {
            $this->preSort();
        }

        foreach ($this->locales as $locale) {
            if (array_key_exists($locale, $this->categories)) {
                $this->total += \count($this->categories[$locale]);
            }
        }

        $this->debug("\n");

        $this->debug("Total lines\t" . $this->total . "\n");
        $this->debug("Lines ignored \t" . $this->ignored . "\n");
        $this->debug("Not processed\t" . $this->notAdded . "\n");
        $this->debug("Total lines\t" . $this->total . "\n\n");

        $this->debug("Reorganizing categories...\n");

        $allCategories = [];

        // Loop through all previously indexed categories
        foreach ($this->categories as $catLocale => $cats) {

            /* @var array $cats */
            foreach ($cats as $categoryName => $values) {
                // Get our categoryName and previous set up data

                // See if the category already exists if so add new data else initiate it
                if (array_key_exists($values['id'], $allCategories)) {
                    // Add translation of the category to the correct locale key
                    $allCategories[$values['id']][$catLocale] = $categoryName;

                    $this->added++;
                    $this->progress($this->added, $this->total);
                } else {
                    // New category instantiate array

                    if (array_key_exists('duplicate', $values)) {
                        // Key is duplicate split
                        list( , $categoryName) = explode('<>', $categoryName);
                    }
                    // Default ID for the parent_id if a parent was found it will update it
                    $parent_id = 0;
                    if (array_key_exists('parent_name', $values)) {
                        $parent_id = $this->categories[$values['locale']][$values['parent_name']]['id'];
                    }

                    // Add parent ID
                    $allCategories[$values['id']]['parent_id'] = $parent_id;

                    // Add translation
                    $allCategories[$values['id']][$values['locale']] = $categoryName;

                    $this->added++;
                    $this->progress($this->added, $this->total);
                }
            }
        }

        $this->categories = $allCategories;

        unset($allCategories);

        $this->debug("\n");

        return $this;
    }

    /**
     * @return Sorter $this
     */
    private function preSort(): self
    {
        foreach ($this->locales as $locale) {

            $fileName = 'https://www.google.com/basepages/producttype/taxonomy-with-ids.' . $locale . '.txt';
            $file = file_get_contents($fileName);

            // Init our array
            $this->categories[$locale] = [];

            // Get our lines
            $lines = explode("\n", $file);

            $this->debug('Processing file ' . $fileName . "\t" . \count($lines) . "\n");

            // Loop through lines of the file
            foreach ($lines as $line) {
                // Filter out empty and the starting line with #
                if ($line !== '' && substr($line, 0, 1) !== '#') {

                    // Explode our categories
                    $lineParts = explode(' > ', $line);

                    if (\count($lineParts) > 1) {
                        // Has a parent

                        $categoryName = end($lineParts);

                        $fullParentName = $lineParts[\count($lineParts) - 2];
                        if (\count($lineParts) === 2) {
                            // Parent is dirty with the ID in it
                            list($id, $parentName) = explode(' - ', $lineParts[0]);
                        } else {
                            // Just get ID
                            list($id) = explode(' - ', $lineParts[0]);
                            $parentName = $fullParentName;
                        }

                        // Typecast
                        $id = (int)$id;

                        // We don't want to override our category, check it
                        if (array_key_exists($categoryName, $this->categories[$locale])) {
                            // Insert it with a unique identifier to avoid collisions based on name in our $this->categories array
                            $this->categories[$locale][$id . '<>' . $categoryName] = [
                                'id'          => $id,
                                'parent_name' => $parentName,
                                'locale'      => $locale,
                                'duplicate'   => true,
                            ];

                            $this->debug('Key exists \'' . $categoryName . ' in ' . $locale . '\' array, id: ' . $id . "\n");
                            $this->debug('Inserted as duplicate with key: ' . $id . '<>' . $categoryName . "\n");
                        } else {
                            // Insert our category
                            $this->categories[$locale][$categoryName] = [
                                'id'          => $id,
                                'parent_name' => $parentName,
                                'locale'      => $locale,
                            ];

                            // Small check to see if it was actually added, only ever shown up when using chinese locale
                            if (!\is_array($this->categories[$locale][$categoryName])) {
                                $this->debug("CATEGORY WAS NOT INSERTED\n");
                                $this->notAdded++;
                            }
                        }
                    } else {
                        // Top level category
                        list($id, $categoryName) = explode(' - ', $lineParts[0]);

                        // Typecast
                        $id = (int)$id;

                        // We don't want to override our category, check it
                        if (array_key_exists($categoryName, $this->categories[$locale])) {
                            // Insert it with a unique identifier to avoid collisions based on name in our $this->categories array
                            $this->categories[$locale][$id . '<>' . $categoryName] = [
                                'id'        => $id,
                                'locale'    => $locale,
                                'duplicate' => true,
                            ];

                            $this->debug('Key exists \'' . $categoryName . ' in ' . $locale . '\' array, id: ' . $id . "\n");
                            $this->debug('Inserted as duplicate with key: ' . $id . '<>' . $categoryName . "\n");
                        } else {
                            $this->categories[$locale][$categoryName] = [
                                'id'     => $id,
                                'locale' => $locale,
                            ];

                            // Small check to see if it was actually added, only ever shown up when using chinese locale
                            if (!\is_array($this->categories[$locale][$categoryName])) {
                                $this->debug("CATEGORY WAS NOT INSERTED\n");
                                $this->notAdded++;
                            }
                        }
                    }
                } else {
                    $this->debug("Ignoring line:\t" . ($line === '' ? '(empty)' : $line) . "\n");
                    $this->ignored++;
                }
            }
            $this->debug("Done\n");
        }

        return $this;
    }

    public function debug($var)
    {
        if ($this->debug === true) {
            if (PHP_SAPI === 'cli') {
                // Console output
                if (\is_array($var)) {
                    print_r($var);
                } else {
                    echo $var;
                }
            } else {
                // Browser output
                if (\is_array($var)) {
                    echo '<pre>';
                    print_r($var);
                    echo '</pre>';
                } else {
                    echo str_replace("\n", '<br/>', $var);
                }
            }
        }
    }

    /**
     * @param $done
     * @param $total
     */
    private function progress($done, $total)
    {
        if (PHP_SAPI === 'cli') {
            $perc = floor(($done / $total) * 100);
            $left = 100 - $perc;
            $write = sprintf("\033[0G\033[2K[\033[32m%'={$perc}s%-{$left}s\033[0m] - $perc%% - $done/$total", '', '');
            fwrite(STDERR, $write);
        }
    }

    /**
     * Sort all categories by parent ID
     *
     * @return Sorter $this
     */
    public function sortByParent(): Sorter
    {
        usort($this->categories, function ($item1, $item2) {
            return $item1['parent_id'] <=> $item2['parent_id'];
        });

        return $this;
    }

    /**
     * Sort all categories by category ID
     *
     * @return Sorter $this
     */
    public function sortByCategoryId(): Sorter
    {
        ksort($this->categories);

        return $this;
    }

    /**
     * @param bool $pretty
     * @return string
     */
    public function outputJSON($pretty = false): string
    {
        if ($pretty) {
            return json_encode($this->categories, JSON_PRETTY_PRINT);
        }

        return json_encode($this->categories);
    }
}
