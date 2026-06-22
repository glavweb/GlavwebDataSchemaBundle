schema:
    class: <?php echo $modelClass."\n"; ?>
    db_driver: orm
    properties:
<?php

foreach ($fields as $field => $type) {
    echo "        {$field}: # {$type}\n";
}

    foreach ($associations as $field => $associationData) {
        echo "        {$field}: # {$associationData['class']}\n";
        echo "            schema: {$associationData['data_schema']}\n";
        echo "            join: left\n";
    }

    ?>