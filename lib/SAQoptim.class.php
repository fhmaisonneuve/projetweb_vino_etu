<?php
/**
 * Class SAQ
 * Classe qui génère ma connection à MySQL à travers un singleton
 *
 * @author Jonathan Martel
 * @version 1.0
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

		$this->stmt = $this->_db->prepare(
			"INSERT INTO vino__bouteille (nom, type, image, code_saq, pays, description, prix_saq, url_saq, url_img, format) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
		);

		if (!$this->stmt) {
			echo "Echec de la préparation : (" . $this->_db->errno . ") " . $this->_db->error;
		}
	}

	public function getProduits($nombre = 24, $page = 1)
	{
		$url = "https://www.saq.com/fr/produits/vin/vin-rouge?p=" . $page . "&product_list_limit=" . $nombre . "&product_list_order=name_asc";

		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:60.0) Gecko/20100101 Firefox/60.0',
			CURLOPT_ENCODING => 'gzip, deflate',
			CURLOPT_HTTPHEADER => [
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language: en-US,en;q=0.5',
				'Accept-Encoding: gzip, deflate',
				'Connection: keep-alive',
				'Upgrade-Insecure-Requests: 1',
			],
		]);

		self::$_webpage = curl_exec($ch);
		self::$_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$doc = new DOMDocument();
		@$doc->loadHTML(self::$_webpage);

		$elements = $doc->getElementsByTagName("li");
		$i = 0;

		foreach ($elements as $node) {
			if (strpos($node->getAttribute('class'), "product-item") !== false) {
				$info = $this->recupereInfo($node);

				$retour = $this->ajouteProduit($info);
				echo "<p>{$info->nom}<br>Code de retour : {$retour->raison}<br></p>";

				if ($retour->succes) {
					$i++;
				}
			}
		}
		return $i;
	}

	private function recupereInfo($noeud)
	{
		$info = new stdClass();

		$info->img = $noeud->getElementsByTagName("img")->item(0)->getAttribute('src');
		$info->url = $noeud->getElementsByTagName("a")->item(0)->getAttribute('href');
		$info->nom = self::nettoyerEspace(trim($noeud->getElementsByTagName("a")->item(1)->textContent));

		$strongElements = $noeud->getElementsByTagName("strong");
		$divElements = $noeud->getElementsByTagName("div");
		$spanElements = $noeud->getElementsByTagName("span");

		foreach ($strongElements as $node) {
			if ($node->getAttribute('class') == 'product product-item-identity-format') {
				$info->desc = new stdClass();
				$info->desc->texte = self::nettoyerEspace($node->textContent);
				$aDesc = explode("|", $info->desc->texte);

				if (count($aDesc) == 3) {
					$info->desc->type = trim($aDesc[0]);
					$info->desc->format = trim($aDesc[1]);
					$info->desc->pays = trim($aDesc[2]);
				}
			}
		}

		foreach ($divElements as $node) {
			if ($node->getAttribute('class') == 'saq-code' && preg_match("/\d+/", $node->textContent, $aRes)) {
				$info->desc->code_SAQ = trim($aRes[0]);
			}
		}

		foreach ($spanElements as $node) {
			if ($node->getAttribute('class') == 'price') {
				$info->prix = trim($node->textContent);
			}
		}

		return $info;
	}


	private function ajouteProduit($bte)
	{
		$retour = new stdClass();
		$retour->succes = false;
		$retour->raison = '';

		$query = "SELECT id FROM vino__type WHERE type = ?";
		$stmtType = $this->_db->prepare($query);
		$stmtType->bind_param("s", $bte->desc->type);
		$stmtType->execute();

		$result = $stmtType->get_result();
		if ($result->num_rows === 1) {
			$type = $result->fetch_assoc();
			$type = $type['id'];

			$query = "SELECT id FROM vino__bouteille WHERE code_saq = ?";
			$stmtBouteille = $this->_db->prepare($query);
			$stmtBouteille->bind_param("s", $bte->desc->code_SAQ);
			$stmtBouteille->execute();

			$resultBouteille = $stmtBouteille->get_result();
			if ($resultBouteille->num_rows < 1) {
				$this->stmt->bind_param(
					"sissssisss",
					$bte->nom,
					$type,
					$bte->img,
					$bte->desc->code_SAQ,
					$bte->desc->pays,
					$bte->desc->texte,
					$bte->prix,
					$bte->url,
					$bte->img,
					$bte->desc->format
				);
				$retour->succes = $this->stmt->execute();
				$retour->raison = self::INSERE;
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
}
?>