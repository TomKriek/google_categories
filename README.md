# google_categories
Sorting google categories with parent_id

Pull repo and run command ```php sort_categories.php [-p|-c] [-v]```

```-v``` verify that every category has the same amount of locales as there are locales enabled. To ensure each category has a translation.

```-p``` sort by `parent_id` uses `usort` with a callback to sort by `parent_id`

```-c``` sort by `category_id` uses `ksort` to sort the array based on the key

Will generate a JSON file with all categories sorted.

WIP for japanese and chinese locale because of their language constructs

```iso.php``` file contains enabled locales to retrieve a google taxonomy file from and parse it.
