<?php

function progress($done, $total)
{
    if (php_sapi_name() != 'cli') {
        return false;
    }

    $perc = floor(($done / $total) * 100);
    $left = 100 - $perc;
    $write = sprintf("\033[0G\033[2K[\033[32m%'={$perc}s%-{$left}s\033[0m] - $perc%% - $done/$total", "", "");
    fwrite(STDERR, $write);
}

$isos = require("iso.php");
foreach ($isos as $iso) {
    $locales[$iso]['short'] = $iso;
}

$lines = [];
$categories = [];
$categoryList = [];
$notAdded = 0;
$ignored = 0;

// Loop through all locales
foreach ($locales as $locale => $localeValues) {
    // Retrieve our file

    $fileName = "https://www.google.com/basepages/producttype/taxonomy-with-ids." . $localeValues['short'] . ".txt";
    $file = file_get_contents($fileName);

    // Init our array
    $categories[$localeValues['short']] = [];

    // Get our lines
    $lines = explode("\n", $file);

    echo "Processing file " . $fileName . "\t" . count($lines) . "\n";

    // Loop through lines of the file
    foreach ($lines as $line) {
        // Filter out empty and the starting line with #
        if ($line != '' && substr($line, 0, 1) != '#') {

            // Explode our categories
            $lineParts = explode(" > ", $line);

            if (count($lineParts) > 1) {
                // Has a parent

                $categoryName = end($lineParts);

                $fullParentName = $lineParts[count($lineParts) - 2];
                if (count($lineParts) == 2) {
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
                if (array_key_exists($categoryName, $categories[$localeValues['short']])) {
                    // Insert it with a unique identifier to avoid collisions based on name in our $categories array
                    $categories[$localeValues['short']][$id."<>".$categoryName] = [
                        'id'          => $id,
                        'parent_name' => $parentName,
                        'locale' => $localeValues['short'],
                        'duplicate' => true,
                    ];
                    echo 'Key exists \'' . $categoryName . '\' in \''. $localeValues['short'] . '\' array, id: ' . $id . "\n";
                    echo "Inserted as duplicate with key: ". $id."<>".$categoryName . "\n";
                } else {
                    // Insert our category
                    $categories[$localeValues['short']][$categoryName] = [
                        'id'          => $id,
                        'parent_name' => $parentName,
                        'locale' => $localeValues['short'],
                    ];

                    // Small check to see if it was actually added, only ever shown up when using chinese locale
                    if(!is_array($categories[$localeValues['short']][$categoryName])){
                        echo "\033[31mCATEGORY WAS NOT INSERTED\033[0m\n";
                    }
                }
            } else {
                // Top level category
                list($id, $categoryName) = explode(' - ', $lineParts[0]);

                // Typecast
                $id = (int)$id;

                // We don't want to override our category, check it
                if (array_key_exists($categoryName, $categories[$localeValues['short']])) {
                    // Insert it with a unique identifier to avoid collisions based on name in our $categories array
                    $categories[$localeValues['short']][$id."<>".$categoryName] = [
                        'id'          => $id,
                        'locale' => $localeValues['short'],
                        'duplicate' => true,
                    ];
                    echo 'Key exists \'' . $categoryName . '\' in \''. $localeValues['short'] . '\' array, id: ' . $id . "\n";
                    echo "Inserted as duplicate with key: ". $id."<>".$categoryName . "\n";
                } else {
                    $categories[$localeValues['short']][$categoryName] = [
                        'id' => $id,
                        'locale' => $localeValues['short'],
                    ];

                    // Small check to see if it was actually added, only ever shown up when using chinese locale
                    if(!is_array($categories[$localeValues['short']][$categoryName])){
                        echo "\033[31mCATEGORY WAS NOT INSERTED\033[0m\n";
                    }
                }
            }
        } else {
            echo "Ignoring line:\t" . ($line == '' ? '(empty)' : $line) . "\n";
            $ignored++;
        }
    }

    echo "\033[32mDone.\033[0m\n";
}

/**
 * All categories have now been indexed into one array which consists of the keys enabled in the iso.php file
 *
 * Uncomment the lines below to output and view the categories array, it will have a key for each locale.
 * The idea of using the category names as indices is so we can easily retrieve value based on a search by name
 * We know the category name of our parent category and since we have now indexed all of the categories we can search for a category id by doing the following
 * $parent_id = $categories['nl-NL']['ParentCategoryName']['id'];
 *
 * We will use this to discover the parent_ids of the categories in code block below
 */

//if(php_sapi_name() == 'cli'){
//    var_dump($categories);
//}else{
//    echo '<pre>';
//    print_r($categories);
//    echo '</pre>';
//}

// Some statistics and output
$total = 0;
$added = 0;
foreach ($locales as $locale => $localeValues) {
    if (array_key_exists($localeValues['short'], $categories)) {
        $total += count($categories[$localeValues['short']]);
    }
}

echo "\n";
echo "Total lines\t" . $total . "\n";
echo "Lines ignored \t" . $ignored . "\n";
echo "Not processed\t" . $notAdded . "\n";

echo "\nReorganizing categories...\n";

// Our final array which will have category_ids as the index and keys id, parent_id and keys for each locale
$allCategories = [];

// Loop through all previously indexed categories
foreach ($categories as $catLocale => $cats) {
    foreach ($cats as $categoryName => $values) {
        // Get our categoryName and previous set up data

        // See if the category already exists if so add new data else initiate it
        if (array_key_exists($values['id'], $allCategories)) {
            // Add translation of the category to the correct locale key
            $allCategories[$values['id']][$catLocale] = $categoryName;

            $added++;
            progress($added, $total);
        } else {
            // New category instantiate array

            if(array_key_exists('duplicate', $values)){
                // Key is duplicate split
                list($id, $categoryName) = explode('<>', $categoryName);
            }
            // Default ID for the parent_id if a parent was found it will update it
            $parent_id = 0;
            if (array_key_exists('parent_name', $values)) {
                $parent_id = $categories[$values['locale']][$values['parent_name']]['id'];
            }

            // Add parent ID
            $allCategories[$values['id']]['parent_id'] = $parent_id;

            // Add translation
            $allCategories[$values['id']][$values['locale']] = $categoryName;

            $added++;
            progress($added, $total);
        }
    }
}

echo "\n";

if(in_array('-v', $argv)){
    $discrepancies = 0;
    echo "\n";
    echo "Checking all locales for each category...\n\n";

    foreach($allCategories as $category_id => $category){
        if(count($category) != count($locales) + 1 ){

            echo "\033[31m$category_id does not have all locales\033[0m\n";

            $missing = false;
            foreach($locales as $locale => $localeValues){

                if(!array_key_exists($locale, $category)){
                    echo $locale . ", ";
                    $missing = true;
                }
            }

            if($missing){
                echo " missing from category translations\n\n";
                $discrepancies++;
            }

            var_dump($category);
            echo "\n";
        }
    }

    if($discrepancies === 0){
        echo "\033[32mNo discrepancies found in the amount of locales for all categories!\033[0m\n\n";
    }
}

unset($categories);

// Don't use both of these sorting methods together use one
if(in_array('-c', $argv) && !in_array('-p', $argv)){
    // Sort by category_id
    ksort($allCategories);
}

if(in_array('-p', $argv) && !in_array('-c', $argv)){
    // Sort by parent_id, this will allow for proper relations set up in a database
    usort($allCategories, function ($item1, $item2) {
        return $item1['parent_id'] <=> $item2['parent_id'];
    });
}

unlink("categories.json");
file_put_contents("categories.json", json_encode($allCategories, JSON_PRETTY_PRINT));
