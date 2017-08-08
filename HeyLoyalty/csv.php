<?php 

include(dirname(__FILE__) . '/src/Spout/Autoloader/autoload.php');

use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Common\Type;

function csvmakeExcel($excel_data){
    $writer = WriterFactory::create(Type::XLSX);
    $writer->openToFile('php://output');
    $writer->addRows($excel_data);
    $writer->close();
}
