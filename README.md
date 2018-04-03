# google_categories
Sorting google categories with parent_id

This was meant to set up a relation between categories so they can easily be added to a database.

WIP for japanese and chinese locale because of their language constructs

```iso.php``` file contains enabled locales to retrieve a google taxonomy file from and parse it.

Example of how to use the sorter OOP style
```
<?php
   
require __DIR__ . '/src/Sorter.php';
   
$json = (new \TomKriek\GoogleCategories\Sorter([], true))->sort()->sortByParent()->outputJSON(true);
```
