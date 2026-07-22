require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';

$connection = Application::getConnection();
$sqlHelper = $connection->getSqlHelper();

function importMarks($connection, $sqlHelper)
{
 $file = $_SERVER['DOCUMENT_ROOT'].'/upload/catalog_to/marks.csv';
 if (!file_exists($file)) {
 echo "Marks file not found\n";
 return;
 }
 if (($h = fopen($file, 'r')) !== false) {
 // пропускаем заголовок fgetcsv($h,0, ',');

 while (($row = fgetcsv($h,0, ',')) !== false) {
 list($marcaId, $marcaName) = $row;

 $marcaId = (int)$marcaId;
 $marcaName = $sqlHelper->forSql($marcaName);

 $connection->queryExecute("
 INSERT INTO lider_auto_brand (ID, NAME)
 VALUES ($marcaId, '$marcaName')
 ON DUPLICATE KEY UPDATE NAME = VALUES(NAME)
 ");
 }
 fclose($h);
 echo "Marks imported\n";
 }
}

function importModels($connection, $sqlHelper)
{
 $file = $_SERVER['DOCUMENT_ROOT'].'/upload/catalog_to/models.csv';
 if (!file_exists($file)) {
 echo "Models file not found\n";
 return;
 }
 if (($h = fopen($file, 'r')) !== false) {
 fgetcsv($h,0, ',');

 while (($row = fgetcsv($h,0, ',')) !== false) {
 list($marcaId, $marcaName, $modelId, $modelName, $yearFrom, $yearTo) = $row;

 $marcaId = (int)$marcaId;
 $modelId = (int)$modelId;
 $modelName = $sqlHelper->forSql($modelName);
 $yearFrom = $yearFrom !== '' ? (int)$yearFrom : 'NULL';
 $yearTo = $yearTo !== '' ? (int)$yearTo : 'NULL';

 $connection->queryExecute("
 INSERT INTO lider_auto_model (ID, BRAND_ID, NAME, YEAR_FROM, YEAR_TO)
 VALUES ($modelId, $marcaId, '$modelName', $yearFrom, $yearTo)
 ON DUPLICATE KEY UPDATE BRAND_ID = VALUES(BRAND_ID),
 NAME = VALUES(NAME),
 YEAR_FROM = VALUES(YEAR_FROM),
 YEAR_TO = VALUES(YEAR_TO)
 ");
 }
 fclose($h);
 echo "Models imported\n";
 }
}

function importModifications($connection, $sqlHelper)
{
 $file = $_SERVER['DOCUMENT_ROOT'].'/upload/catalog_to/modifications.csv';
 if (!file_exists($file)) {
 echo "Modifications file not found\n";
 return;
 }
 if (($h = fopen($file, 'r')) !== false) {
 fgetcsv($h,0, ',');

 while (($row = fgetcsv($h,0, ',')) !== false) {
 list(
 $modelId,
 $modelName,
 $modificationId,
 $fullName,
 $engineCode,
 $constructionType,
 $fuel,
 $horsePower,
 $engineCapacity,
 $numberOfCylinders,
 $valves,
 $valvesTotal,
 $motorType,
 $startDate,
 $endDate ) = $row;

 $modelId = (int)$modelId;
 $modificationId = (int)$modificationId;
 $fullName = $sqlHelper->forSql($fullName);
 $engineCode = $sqlHelper->forSql($engineCode);
 $constructionType = $sqlHelper->forSql($constructionType);
 $fuel = $sqlHelper->forSql($fuel);
 $horsePower = (int)$horsePower;
 $engineCapacity = $engineCapacity !== '' ? (float)$engineCapacity : 'NULL';
 $numberOfCylinders = (int)$numberOfCylinders;
 $valves = (int)$valves;
 $valvesTotal = (int)$valvesTotal;
 $motorType = $sqlHelper->forSql($motorType);

 $startDateSql = $startDate !== '' ? "'".date('Y-m-d H:i:s', strtotime($startDate))."'" : 'NULL';
 $endDateSql = $endDate !== '' ? "'".date('Y-m-d H:i:s', strtotime($endDate))."'" : 'NULL';

 $connection->queryExecute("
 INSERT INTO lider_auto_modification (ID, MODEL_ID, FULL_NAME, ENGINE_CODE, BODY_TYPE, FUEL,
 HORSE_POWER, ENGINE_CAPACITY, CYLINDERS, VALVES_PER_CYLINDER,
 VALVES_TOTAL, MOTOR_TYPE, START_DATE, END_DATE)
 VALUES ($modificationId, $modelId, '$fullName', '$engineCode', '$constructionType', '$fuel',
 $horsePower, $engineCapacity, $numberOfCylinders, $valves,
 $valvesTotal, '$motorType', $startDateSql, $endDateSql)
 ON DUPLICATE KEY UPDATE MODEL_ID = VALUES(MODEL_ID),
 FULL_NAME = VALUES(FULL_NAME),
 ENGINE_CODE = VALUES(ENGINE_CODE),
 BODY_TYPE = VALUES(BODY_TYPE),
 FUEL = VALUES(FUEL),
 HORSE_POWER = VALUES(HORSE_POWER),
 ENGINE_CAPACITY = VALUES(ENGINE_CAPACITY),
 CYLINDERS = VALUES(CYLINDERS),
 VALVES_PER_CYLINDER = VALUES(VALVES_PER_CYLINDER),
 VALVES_TOTAL = VALUES(VALVES_TOTAL),
 MOTOR_TYPE = VALUES(MOTOR_TYPE),
 START_DATE = VALUES(START_DATE),
 END_DATE = VALUES(END_DATE)
 ");
 }
 fclose($h);
 echo "Modifications imported\n";
 }
}

function importItems($connection, $sqlHelper)
{
 $file = $_SERVER['DOCUMENT_ROOT'].'/upload/catalog_to/items.csv';
 if (!file_exists($file)) {
 echo "Items file not found\n";
 return;
 }
 if (($h = fopen($file, 'r')) !== false) {
 fgetcsv($h,0, ',');

 while (($row = fgetcsv($h,0, ',')) !== false) {
 list(
 $modificationId,
 $fullName,
 $itemId,
 $itemName,
 $partNumber,
 $quantity,
 $comment,
 $isNecessary,
 $manufacturerId,
 $manufacturerName,
 $categoryId ) = $row;

 $modificationId = (int)$modificationId;
 $itemId = (int)$itemId;
 $itemName = $sqlHelper->forSql($itemName);
 $partNumber = $sqlHelper->forSql($partNumber);
 $quantity = (int)$quantity;
 $comment = $sqlHelper->forSql($comment);
 $isNecessary = strtoupper(trim($isNecessary)) === 'TRUE' ? 'Y' : 'N';
 $manufacturerId = (int)$manufacturerId;
 $manufacturerName = $sqlHelper->forSql($manufacturerName);
 $categoryId = (int)$categoryId;

 $connection->queryExecute("
 INSERT INTO lider_auto_item (ID, MODIFICATION_ID, NAME, PART_NUMBER, QUANTITY,
 COMMENT_TEXT, IS_NECESSARY, MANUFACTURER_ID,
 MANUFACTURER_NAME, CATEGORY_ID)
 VALUES ($itemId, $modificationId, '$itemName', '$partNumber', $quantity,
 '$comment', '$isNecessary', $manufacturerId,
 '$manufacturerName', $categoryId)
 ON DUPLICATE KEY UPDATE MODIFICATION_ID = VALUES(MODIFICATION_ID),
 NAME = VALUES(NAME),
 PART_NUMBER = VALUES(PART_NUMBER),
 QUANTITY = VALUES(QUANTITY),
 COMMENT_TEXT = VALUES(COMMENT_TEXT),
 IS_NECESSARY = VALUES(IS_NECESSARY),
 MANUFACTURER_ID = VALUES(MANUFACTURER_ID),
 MANUFACTURER_NAME = VALUES(MANUFACTURER_NAME),
 CATEGORY_ID = VALUES(CATEGORY_ID)
 ");
 }
 fclose($h);
 echo "Items imported\n";
 }
}

function importOils($connection, $sqlHelper)
{
 $file = $_SERVER['DOCUMENT_ROOT'].'/upload/catalog_to/oils.csv';
 if (!file_exists($file)) {
 echo "Oils file not found\n";
 return;
 }
 if (($h = fopen($file, 'r')) !== false) {
 fgetcsv($h,0, ',');

 while (($row = fgetcsv($h,0, ',')) !== false) {
 list(
 $modificationId,
 $fullName,
 $groupName,
 $originalName,
 $artNumber,
 $volume,
 $catalogId,
 $commentName,
 $manufacturerId,
 $manufacturerName,
 $orderPosition ) = $row;

 $modificationId = (int)$modificationId;
 $groupName = $sqlHelper->forSql($groupName);
 $originalName = $sqlHelper->forSql($originalName);
 $artNumber = $sqlHelper->forSql($artNumber);
 $volume = $volume !== '' ? (float)$volume : 'NULL';
 $catalogId = (int)$catalogId;
 $commentName = $sqlHelper->forSql($commentName);
 $manufacturerId = (int)$manufacturerId;
 $manufacturerName = $sqlHelper->forSql($manufacturerName);
 $orderPosition = (int)$orderPosition;

 $connection->queryExecute("
 INSERT INTO lider_auto_oil (MODIFICATION_ID, GROUP_NAME, ORIGINAL_NAME, ART_NUMBER,
 VOLUME, CATALOG_ID, COMMENT_NAME, MANUFACTURER_ID,
 MANUFACTURER_NAME, ORDER_POSITION)
 VALUES ($modificationId, '$groupName', '$originalName', '$artNumber',
 $volume, $catalogId, '$commentName', $manufacturerId,
 '$manufacturerName', $orderPosition)
 ");
 }
 fclose($h);
 echo "Oils imported\n";
 }
}

function importSpecs($connection, $sqlHelper)
{
 $file = $_SERVER['DOCUMENT_ROOT'].'/upload/catalog_to/specs.csv';
 if (!file_exists($file)) {
 echo "Specs file not found\n";
 return;
 }
 if (($h = fopen($file, 'r')) !== false) {
 fgetcsv($h,0, ',');

 while (($row = fgetcsv($h,0, ',')) !== false) {
 list(
 $modificationId,
 $fullName,
 $name,
 $seoUrl,
 $volume,
 $comment,
 $catalogItemId,
 $properties ) = $row;

 $modificationId = (int)$modificationId;
 $name = $sqlHelper->forSql($name);
 $seoUrl = $sqlHelper->forSql($seoUrl);
 $volume = $volume !== '' ? (float)$volume : 'NULL';
 $comment = $sqlHelper->forSql($comment);
 $catalogItemId = $catalogItemId !== '' ? (int)$catalogItemId : 'NULL';
 $properties = $sqlHelper->forSql($properties);

 $connection->queryExecute("
 INSERT INTO lider_auto_spec (MODIFICATION_ID, NAME, SEO_URL, VOLUME,
 COMMENT_TEXT, CATALOG_ITEM_ID, PROPERTIES)
 VALUES ($modificationId, '$name', '$seoUrl', $volume,
 '$comment', $catalogItemId, '$properties')
 ");
 }
 fclose($h);
 echo "Specs imported\n";
 }
}

importMarks($connection, $sqlHelper);
importModels($connection, $sqlHelper);
importModifications($connection, $sqlHelper);
importItems($connection, $sqlHelper);
importOils($connection, $sqlHelper);
importSpecs($connection, $sqlHelper);

echo "Done\n";