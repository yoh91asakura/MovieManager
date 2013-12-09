<?php
require_once 'AutoRestObject.php';
require_once 'Conf.php';

$pdo = new PDO('mysql:host=localhost;dbname=cinema2013', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$object = new AutoRestObject($pdo);
$object->dumpXml();

// Simuler la lenteur d'un serveur

init();
switch ($_SERVER['REQUEST_METHOD']) {
    case "GET":
        do_get();
        break;
    case "PUT":
        do_put();
        break;
    case "DELETE":
        do_delete();
        break;
    default:
        send_status(405);
        die();
}

// Initialiser $id
function init() {
    global $id;
    if (empty($_GET["id"])) {
        exit_error(400, "idRequis");
    }
    else {
        $id = $_GET["id"];
        if (!is_numeric($id)) {
            exit_error(400, "idNonEntierPositif");
        }
        $id = 0 + $id;
        if (!is_int($id) || $id <= 0) {
            exit_error(400, "idNonEntierPositif");
        }
    }
}

// En reponse a une methode GET
function do_get() {
    global $id;
    try {
        $db = getConnexion();
        $sql = "SELECT id_artiste, nom, prenom FROM artiste WHERE id_artiste=:id_artiste";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(":id_artiste", $id);
        $result = $stmt->execute();
        if ($result) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row != null) {
                $dom = new DOMDocument();
                $artiste = $dom->createElement("artiste");
                $dom->appendChild($artiste);
                $artiste->setAttribute("id", $row['id_artiste']);
                $artiste->setAttribute("nom", utf8_encode($row['nom']));
                $artiste->setAttribute("prenom", utf8_encode($row['prenom']));
                // Pour forcer l'encodage à utf8
                header("Content-type: text/xml; charset=utf-8");
                print $dom->saveXML();
            }
            else {
                exit_error(404, null);
            }
        }
        else {
            exit_error(500, print_r($db->errorInfo(), true));
        }
    }
    catch (PDOException $e) {
        exit_error(500, $e->getMessage());
    }
}

// Mise a jour d'un artiste
function do_put() {
    global $id;
    if (!is_admin()) {
        exit_error(401, "mustBeAdmin");
    }
    $erreurs = array();
    // Les parametres passés en put
    parse_str(file_get_contents("php://input"), $_PUT);
    if (empty($_PUT["nom"]) || empty($_PUT["prenom"])) {
        exit_error(400, "nomOuPrenomVide");
    }
    else {
        try {
            $db = getConnexion();
            $sql = "UPDATE artiste SET nom=:nom, prenom=:prenom WHERE id_artiste=:id";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(":nom", ucwords(trim($_PUT["nom"])));
            $stmt->bindValue(":prenom", ucwords(trim($_PUT["prenom"])));
            $stmt->bindValue(":id", $_GET["id"]);
            $ok = $stmt->execute();
            if ($ok) {
                $nb = $stmt->rowCount();
                if ($nb == 0) {
                    // L'artiste n'existe pas ou ses valeurs sont inchangees.
                    // Verifier si c'est l'un ou l'autre
                    $sql = "SELECT id_artiste FROM artiste WHERE id_artiste=:id";
                    $stmt = $db->prepare($sql);
                    $stmt->bindValue(":id", $_GET["id"]);
                    $ok = $stmt->execute();
                    if ($stmt->fetch() == null) {
                        send_status(404);
                    }
                    else {
                        send_status(204);
                    }
                }
                else {
                    send_status(204);
                }
            }
            else {
                $erreur = $stmt->errorInfo();
                // si doublon
                if ($erreur[1] == 1062) {
                    exit_error(409, "doublonNomPrenom");
                }
                else {
                    exit_error(409, $erreur[1]." : ".$erreur[2]);
                }
            }
        }
        catch (PDOException $e) {
            exit_error(500, $e->getMessage());
        }
    }
}

function do_delete() {
    global $id;
    if (!is_admin()) {
        exit_error(401);
    }
    if (empty($_GET["id"])) {
        exit_error(400, "idRequired");
    }
    $id = $_GET["id"];
    try {
        $db = getConnexion();
        $sql = "DELETE FROM artiste WHERE id_artiste=:id_artiste";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(":id_artiste", $id);
        $ok = $stmt->execute();
        if ($ok) {
            $nb = $stmt->rowCount();
            if ($nb == 0) {
                send_status(404);
            }
            else {
                send_status(204);
            }
        }
        else {
            $erreur = $stmt->errorInfo();
            // si artiste reference par film (realisateur) ou personnage (acteur)
            if ($erreur[1] == 1451) { // Contrainte de cle etrangere
                exit_error(409, "artisteReferenceParFilmOuPersonnage");
            }
            else {
                exit_error(409, $erreur[1]." : ".$erreur[2]);
            }
        }
    }
    catch (PDOException $e) {
        exit_error(500, $e->getMessage());
    }
}
?>