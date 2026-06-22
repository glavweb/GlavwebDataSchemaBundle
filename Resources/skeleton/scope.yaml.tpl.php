scope:
<?php

foreach ($fields as $field => $type) {
    echo "    {$field}:\n";
}

foreach ($associations as $field => $associationData) {
    echo "    {$field}:\n";
    if ($withAssociationFields) {
        foreach ($associationData['fields'] as $subField => $subAssociationData) {
            echo "        {$subField}:\n";
        }
    } else {
        echo "        id:\n";
    }
}

?>