<?php
/**
 * GaiaEHR (Electronic Health Records)
 * Copyright (C) 2015 TRA NextGen, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class ReportGenerator
{

    private $request;
    public $reportDir;
    public $format;
    private $conn;
    private $site;

    function __construct($site = 'default')
    {
        try
        {
            $this->site = $site;
            if(!defined('_GaiaEXEC')) define('_GaiaEXEC', 1);
            require_once('../../../registry.php');
            require_once("../../../sites/$this->site/conf.php");
            require_once('../../../classes/MatchaHelper.php');
            require_once('../../../classes/Array2XML.php');
        }
        catch(Exception $Error)
        {
            error_log(print_r($Error,true));
            return $Error;
        }
    }

    function setRequest($REQUEST)
    {
        try
        {
            if(!isset($REQUEST)) throw new Exception('No request was sent by the client.');
            if(isset($REQUEST['site'])) $this->site = $REQUEST['site'];
            $this->request = json_decode($REQUEST['params'], true);
            $this->reportDir = $REQUEST['reportDir'];
            $this->format = $REQUEST['format'];
        }
        catch(Exception $Error)
        {
            error_log(print_r($Error,true));
            return $Error;
        }
    }

    function getXSLTemplate()
    {
        try
        {
            $filePointer = "../reports/$this->reportDir/report.xsl";
            if(file_exists($filePointer) && is_readable($filePointer))
            {
                return file_get_contents($filePointer);
            }
            else
            {
                throw new Exception('Error: Not XSLT file was found or readable.');
            }
        }
        catch(Exception $Error)
        {
            error_log(print_r($Error,true));
            return $Error;
        }
    }

    function getXMLDocument()
    {
        try
        {
            $filePointer = "../reports/$this->reportDir/reportStatement.sql";
            if(file_exists($filePointer) && is_readable($filePointer))
            {
                // Important connection parameter, this will allow multiple
                // prepare tags with the same name.
                $this->conn = Matcha::getConn();
                $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

                // Get the report SQL statement content
                $fileContent = file_get_contents($filePointer);

                // Copy all the request variables into the Prepared Values,
                // also check if it came from the grid form and normal form.
                // This because we need to do a POST-PREPARE the SQL statement
                foreach($this->request as $field)
                {
                    $PrepareField[':' . $field['name']]['operator'] = (isset($field['operator']) ? $field['operator'] : '=');
                    $PrepareField[':' . $field['name']]['value'] = $field['value'];
                }

                // Copy all the request filter variables to the XML,
                // also check if it came from the grid form and normal form.
                // This because we need to do a POST-PREPARE the SQL statement
                foreach ($this->request as $field)
                {
                    $ReturnFilter[$field['name']]['operator'] = (isset($field['operator']) ? $field['operator'] : '=');
                    $ReturnFilter[$field['name']]['value'] = $field['value'];
                }

                // Prepare all the variable fields in the SQL Statement
                $PreparedSQL = $this->PostPrepare($fileContent, $PrepareField);
                $Queries = explode(';', $PreparedSQL);

                // Run all the SQL Statement separated by `;` in the file
                foreach($Queries as $Query)
                {
                    if(strlen(trim($Query)) > 0)
                    {
                        $SQL = $this->conn->prepare($Query);
                        $SQL->execute();
                        $records[] = $SQL->fetchAll(PDO::FETCH_ASSOC);
                    }
                }

                $ExtraAttributes['xml-stylesheet'] = 'type="text/xsl" href="report.xsl"';
                Array2XML::init('1.0', 'UTF-8', true, $ExtraAttributes);
                $xml = Array2XML::createXML('records', array(
                    'filters' => $ReturnFilter,
                    'record' => $records[count($records)-1]
                ));
                return $xml->saveXML();
            }
            else
            {
                throw new Exception('Error: Not SQL Statement file was found or readable.');
            }
        }
        catch(Exception $Error)
        {
            error_log(print_r($Error,true));
            return $Error;
        }
    }

    /**
     * Process the SQL statement to put in place the variables [:var] and put the real value
     * also it smart enough to write single quote when it is alpha-numeric and no quotes when
     * is number.
     *
     * @param string $sqlStatement
     * @param array $variables
     * @return mixed|string
     */
    function PostPrepare($sqlStatement = '', $variables = array())
    {
        foreach($variables as $key => $variable)
        {
            $prepareKey = trim($key);
            if(is_numeric($variable['value']))
            {
                $prepareVariable = $variable['value'];
            }
            elseif($variable['value'] == null)
            {
                $prepareVariable = "null";
            }
            else
            {
                $prepareVariable = "'{$variable['value']}'";
            }
            $sqlStatement = str_ireplace($prepareKey, $prepareVariable, $sqlStatement);
            $sqlStatement = str_ireplace($prepareKey.'_operator', $variable['operator'], $sqlStatement);
        }
        return $sqlStatement;
    }
}

/**
 * This will combine the XML and the XSL
 * or generate the HTML, Text
 */
$rg = new ReportGenerator();
$rg->setRequest($_REQUEST);

$date = new DateTime();
$Stamp = $date->format('Ymd-His');

$xslt = new XSLTProcessor();
$xslt->registerPHPFunctions();

switch($rg->format)
{
    case 'html':
        header('Content-Type: application/xslt+xml');
        header('Content-Disposition: inline; filename='.strtolower($rg->reportDir).'-'.$Stamp.'".html"');
        $xslt->importStylesheet(new SimpleXMLElement($rg->getXSLTemplate()));
        echo $xslt->transformToXml(new SimpleXMLElement($rg->getXMLDocument()));
        break;
    case 'pdf':
        require_once('../../../lib/html2pdf_v4.03/html2pdf.class.php');
        $xslt->importStylesheet(new SimpleXMLElement($rg->getXSLTemplate()));
        $html2pdf = new HTML2PDF('P', 'A4', 'en');
        $html2pdf->pdf->SetAuthor('GaiaEHR');
        $html2pdf->WriteHTML($xslt->transformToXml(new SimpleXMLElement($rg->getXMLDocument())));
        $PDFDocument = base64_encode($html2pdf->Output(strtolower($rg->reportDir).'-'.$Stamp.'.pdf', "S"));
        echo '<object data="data:application/pdf;base64,' . $PDFDocument . '" type="application/pdf" width="100%" height="100%"></object>';
        break;
}
