<?php
/**
 * Created by JetBrains PhpStorm.
 * User: gbouchez
 * Date: 02/12/13
 * Time: 16:37
 * To change this template use File | Settings | File Templates.
 */

class AutoRestObject
{
    /**
     * @var \PDO
     */
    protected $pdo = null;
    protected $name = null;
    protected $columns = null;
    protected $isCollection = false;
    protected $acceptedMethods = array('POST', 'GET', 'PUT', 'DELETE');

    protected $nbPerPage = 10;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function handleRequest()
    {
        if (!in_array($_SERVER['REQUEST_METHOD'], $this->acceptedMethods)) {
            $this->sendResponse(405, "Cette action est impossible sur cette ressource.");
        }
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'POST':
                break;
            case 'GET':
                $this->doGet();
                break;
            case 'PUT':
                break;
            case 'DELETE':
                $this->doDelete();
                break;
            default:
                $this->sendResponse(405, "Cette action est impossible sur cette ressource.");
        }
    }

    protected function doDelete()
    {
        if (!$this->isCollection) {
            if (isset($_POST["id_" . $this->getName()])) {
                $queryString = "DELETE FROM " .$this->getName() . " WHERE id_" . $this->getName() . "=:id_" . $this->getName();
                try {
                    $query = $this->pdo->prepare($queryString);
                    $query->bindValue(":id_" . $this->getName(), $_POST["id_" . $this->getName()]);
                    $query->execute();
                    //parti du prof qui servira p e pour less filtres
                    //$ok = $query->execute
//                    if ($ok) {
//                        $nb = $query->rowCount();
//                        if ($nb == 0) {
//                            $this->sendResponse(404, "id non trouvé");
//                        } else {
//                            $this->sendResponse(204);
//                        }
//                    } else {
//                        $erreur = $query->errorInfo();
//                        // si artiste reference par film (realisateur) ou personnage (acteur)
//                        if ($erreur[1] == 1451) { // Contrainte de cle etrangere
//                            $this->sendResponse(409, "artisteReferenceParFilmOuPersonnage");
//                        } else {
//                            $this->sendResponse(409, $erreur[1] . " : " . $erreur[2]);
//                        }
//                    }
                } catch (PDOException $e) {
                    $this->sendResponse(500, $e->getMessage());
                }
            } else {
                $this->sendResponse(400, "Il faut renseigner un ID");
            }
        }
    }


    protected function doGet()
    {
        $mainXml = new DOMDocument('1.0', 'utf-8');
        $toAppend = $mainXml;
        if ($this->isCollection) {
            $queryString = "SELECT * FROM " . $this->getName() . " LIMIT " . ((int)$this->nbPerPage);
            if (!empty($_GET['page'])) {
                $queryString .= " OFFSET " . ((int)(($_GET['page'] - 1) * $this->nbPerPage));
            }
            $query = $this->pdo->prepare($queryString);
            $query->execute();
            $results = $query->fetchAll(PDO::FETCH_ASSOC);
            $toAppend = $mainXml->createElement($this->getName() . 's');
            $toAppend->setAttribute('page', empty($_GET['page']) ? 1 : $_GET['page']);
        } else {
//            $queryString = "SELECT ";
        }

        foreach ($results as $result) {
            $append = $mainXml->createElement($this->getName());
            $append->setAttribute('id', $result[$this->columns['PRIMARY_KEY']['Field']]);
            foreach ($this->columns as $key => $column) {
                if ($key == 'PRIMARY_KEY' || $this->columns['PRIMARY_KEY']['Field'] == $column['Field']) {
                    continue;
                }
                $columnAppend = $mainXml->createElement($key);
                $text = $mainXml->createTextNode($result[$column['Field']]);
                $columnAppend->appendChild($text);
                $append->appendChild($columnAppend);
            }
            $toAppend->appendChild($append);
        }

        $mainXml->appendChild($toAppend);
        echo $mainXml->saveXML();
    }

    public function getName()
    {
        if (is_null($this->name)) {
            preg_match('/\/([a-z]*)[\?\.$]/', $_SERVER['SCRIPT_NAME'], $matches);
            $this->name = $matches[1];
            if (substr($this->name, strlen($this->name) - 1, strlen($this->name)) == 's') {
                $this->isCollection = true;
                $this->name = substr($this->name, 0, strlen($this->name) - 1);
            }
        }
        return $this->name;
    }

    public function checkTable()
    {
        try {
            // Preg match a catché seulement des lettres
            $queryString = "DESCRIBE " . $this->getName();
            $query = $this->pdo->prepare($queryString);
            $query->execute();
            $result = $query->fetchAll(PDO::FETCH_ASSOC);
            $this->columns = array();
            foreach ($result as $column) {
                $this->columns[$column['Field']] = $column;
                if ($column['Key'] == 'PRI') {
                    $this->columns['PRIMARY_KEY'] = & $this->columns[$column['Field']];
                }
            }
        } catch (Exception $e) {
            $this->sendResponse(404, "Cette ressource n'existe pas.");
        }
    }

    public function dumpXml()
    {
        header("Content-type:application/xml");
        $this->checkTable();
        $this->handleRequest();
    }

    public function sendResponse($code, $message)
    {
        http_response_code($code);
        echo $message;
        exit;
    }
}

