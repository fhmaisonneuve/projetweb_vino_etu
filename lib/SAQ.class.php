<?php
/**
 * Class MonSQL
 * Classe qui génère ma connection à MySQL à travers un singleton
 *
 *
 * @author Jonathan Martel
 * @version 1.0
 *
 *
 *
 */
class SAQ extends Modele
{

	const DUPLICATION = 'duplication';
	const ERREURDB = 'erreurdb';
	const INSERE = 'Nouvelle bouteille insérée';

	private static $_webpage;
	private static $_status;
	private $stmt;

	public function __construct()
	{
		parent::__construct();
		if (
			!($this->stmt = $this->_db->prepare("INSERT INTO bouteille(nom, type, image, code_saq, pays, description, prix_saq,
		 url_saq, format, lien_fournisseur, pastille_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"))
		) {
			echo "Echec de la préparation : (" . $mysqli->errno . ") " . $mysqli->error;
		}
	}

	/**
	 * getProduits
	 * @param int $nombre
	 * @param int $debut
	 */
	public function getProduits($nombre = 24, $page = 1)
	{
		$s = curl_init();
		$url = "https://www.saq.com/fr/produits/vin/vin-rouge?p=" . $page . "&product_list_limit=" . $nombre . "&product_list_order=name_asc";

		var_dump($url);

		// TODO: fh: considérer ce url qui récupere tout les types
// TODO: fh: considérer un pagesize de 96 qui semble fonctionner
		//curl_setopt($s, CURLOPT_URL, "http://www.saq.com/webapp/wcs/stores/servlet/SearchDisplay?searchType=&orderBy=&categoryIdentifier=06&showOnly=product&langId=-2&beginIndex=".$debut."&tri=&metaData=YWRpX2YxOjA8TVRAU1A%2BYWRpX2Y5OjE%3D&pageSize=". $nombre ."&catalogId=50000&searchTerm=*&sensTri=&pageView=&facet=&categoryId=39919&storeId=20002");
		//curl_setopt($s, CURLOPT_URL, "https://www.saq.com/webapp/wcs/stores/servlet/SearchDisplay?categoryIdentifier=06&showOnly=product&langId=-2&beginIndex=" . $debut . "&pageSize=" . $nombre . "&catalogId=50000&searchTerm=*&categoryId=39919&storeId=20002");
		//curl_setopt($s, CURLOPT_URL, $url);
		//curl_setopt($s, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($s, CURLOPT_CUSTOMREQUEST, 'GET');
		//curl_setopt($s, CURLOPT_NOBODY, false);
		//curl_setopt($s, CURLOPT_FOLLOWLOCATION, 1);

		// Se prendre pour un navigateur pour berner le serveur de la saq...
		curl_setopt_array(
			$s,
			array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:60.0) Gecko/20100101 Firefox/60.0',
				CURLOPT_ENCODING => 'gzip, deflate',
				CURLOPT_HTTPHEADER => array(
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
					'Accept-Language: en-US,en;q=0.5',
					'Accept-Encoding: gzip, deflate',
					'Connection: keep-alive',
					'Upgrade-Insecure-Requests: 1',
				),
			)
		);

		self::$_webpage = curl_exec($s);
		self::$_status = curl_getinfo($s, CURLINFO_HTTP_CODE);
		curl_close($s);

		$doc = new DOMDocument();
		$doc->recover = true;
		$doc->strictErrorChecking = false;
		@$doc->loadHTML(self::$_webpage);
		$elements = $doc->getElementsByTagName("li");
		$i = 0;
		foreach ($elements as $key => $noeud) {
			//var_dump($noeud -> getAttribute('class')) ;
			//if ("resultats_product" == str$noeud -> getAttribute('class')) {
			if (strpos($noeud->getAttribute('class'), "product-item") !== false) {

				//echo $this->get_inner_html($noeud);
				$info = self::recupereInfo($noeud);
				echo "<p>" . $info->nom;
				$retour = $this->ajouteProduit($info);
				echo "<br>Code de retour : " . $retour->raison . "<br>";
				if ($retour->succes == false) {
					echo "<pre>";
					var_dump($info);
					echo "</pre>";
					echo "<br>";
				} else {
					$i++;
				}
				echo "</p>";
			}
		}

		return $i;
	}

	private function get_inner_html($node)
	{
		$innerHTML = '';
		$children = $node->childNodes;
		foreach ($children as $child) {
			$innerHTML .= $child->ownerDocument->saveXML($child);
		}

		return $innerHTML;
	}
	private function nettoyerEspace($chaine)
	{
		return preg_replace('/\s+/', ' ', $chaine);
	}
	private function recupereInfo($noeud)
	{

		$info = new stdClass();




		$info->img = $noeud->getElementsByTagName("img")->item(0)->getAttribute('src'); //TODO : Nettoyer le lien

		$a_titre = $noeud->getElementsByTagName("a")->item(0);
		$info->url = $a_titre->getAttribute('href');


		//		fh: update sur le code de Jonathan(récupérant a l'occasion l'image de la pastille plutot que l'image de la bouteille)
		$xpath = new DOMXPath($noeud->ownerDocument);
		$bottleImageQuery = "//a[contains(@class, 'product-item-photo')]/span/span/img";
		$bottleImages = $xpath->query($bottleImageQuery);
		if ($bottleImages->length > 0) {
			$info->img = $bottleImages->item(0)->getAttribute('src');
		} else {
			$info->img = "Image de la bouteille non disponible";
		}

		var_dump('image:' . $info->img);


		//lien_fournisseur
		$query = ".//a[contains(@class, 'product-item-link')]";
		$productLinks = $xpath->query($query, $noeud);

		if ($productLinks->length > 0) {
			$info->lien_fournisseur = $productLinks->item(0)->getAttribute('href');
		} else {
			//$info->lien_fournisseur = "Lien du produit non disponible";
		}
		var_dump('lien fournisseru:' . $info->lien_fournisseur);
		$nom = $noeud->getElementsByTagName("a")->item(1)->textContent;
		//var_dump($a_titre);
		$info->nom = self::nettoyerEspace(trim($nom));
		//var_dump($info -> nom);
		// Type, format et pays
		$aElements = $noeud->getElementsByTagName("strong");
		foreach ($aElements as $node) {
			if ($node->getAttribute('class') == 'product product-item-identity-format') {
				$info->desc = new stdClass();
				$info->desc->texte = $node->textContent;
				$info->desc->texte = self::nettoyerEspace($info->desc->texte);
				$aDesc = explode("|", $info->desc->texte); // Type, Format, Pays
				if (count($aDesc) == 3) {

					$info->desc->type = trim($aDesc[0]);
					$info->desc->format = trim($aDesc[1]);
					$info->desc->pays = trim($aDesc[2]);
				}

				$info->desc->texte = trim($info->desc->texte);
			}
		}


		//pastille
		$pastilleInfo = null;
		$aElements = $noeud->getElementsByTagName("div");
		foreach ($aElements as $node) {
			if ($node->getAttribute('class') === 'wrapper-taste-tag') {
				$images = $node->getElementsByTagName('img');
				if ($images->length > 0) {
					$image = $images->item(0);
					$pastilleInfo = new stdClass();
					$pastilleInfo->imgSrc = $image->getAttribute('src');
					$pastilleInfo->imgAlt = $image->getAttribute('alt');
					$pastilleInfo->imgTitle = $image->getAttribute('title');
				}
				break; // un seul élément, donc exit
			}
		}

		if ($pastilleInfo) {
			$info->pastille = $pastilleInfo;
		} else {
			$info->pastille = null;
		}
		var_dump($info->pastille);

		//Code SAQ
		$aElements = $noeud->getElementsByTagName("div");
		foreach ($aElements as $node) {
			if ($node->getAttribute('class') == 'saq-code') {
				if (preg_match("/\d+/", $node->textContent, $aRes)) {
					$info->desc->code_SAQ = trim($aRes[0]);
					$info->code = trim($aRes[0]);
				}



			}
		}

		$aElements = $noeud->getElementsByTagName("span");
		foreach ($aElements as $node) {
			if ($node->getAttribute('class') == 'price') {
				$info->prix = trim($node->textContent);
			}
		}
		//var_dump($info);
		return $info;
	}

	private function ajouteProduit($bte)
	{
		$retour = new stdClass();
		$retour->succes = false;
		$retour->raison = '';

		//var_dump($bte);
		// Récupère le type
		$pastille_id = $this->getPastilleID($bte->pastille); // Appel de la fonction getPastilleID

		var_dump($pastille_id);
		$rows = $this->_db->query("select id from bouteille_type where type = '" . $bte->desc->type . "'");

		if ($rows->num_rows == 1) {
			$type = $rows->fetch_assoc();
			//var_dump($type);
			$type = $type['id'];

			$rows = $this->_db->query("select id from bouteille where code_saq = '" . $bte->desc->code_SAQ . "'");
			if ($rows->num_rows < 1) {
				var_dump($pastille_id);
				$this->stmt->bind_param(
					"sissssisssi",
					$bte->nom,
					$type,
					$bte->img,
					$bte->desc->code_SAQ,
					$bte->desc->pays,
					$bte->desc->texte,
					$bte->prix,
					$bte->url,
					$bte->desc->format,
					$bte->lien_fournisseur,
					$pastille_id

				);
				$retour->succes = $this->stmt->execute();
				$retour->raison = self::INSERE;
				var_dump($this->stmt);
			} else {
				$retour->succes = false;
				$retour->raison = self::DUPLICATION;
			}
		} else {
			$retour->succes = false;
			$retour->raison = self::ERREURDB;

		}
		return $retour;

	}


	public function getPastilleID($pastilleInfo)
	{
		if ($pastilleInfo == null) {
			return null;
		}
		// voir si existe dans db
		if ($stmt = $this->_db->prepare("SELECT id FROM pastille_type WHERE tag_saq = ?")) {
			var_dump($pastilleInfo);
			$stmt->bind_param("s", $pastilleInfo->imgTitle);
			$stmt->execute();
			$result = $stmt->get_result();

			if ($result->num_rows > 0) {
				$row = $result->fetch_assoc();
				return $row['id'];
			} else {

				// n'existe pas, insérer
				if ($insertStmt = $this->_db->prepare('INSERT INTO pastille_type (tag_saq, description, imageURL) VALUES (?, ?, ?)')) {
					$insertStmt->bind_param("sss", $pastilleInfo->imgTitle, $pastilleInfo->imgTitle, $pastilleInfo->imgSrc);
					if ($insertStmt->execute()) {
						return $this->_db->insert_id;
					} else {
						return null;
					}
				}
			}
		} else {
			// Handle the error case if needed
			return null;
		}
	}

}
?>