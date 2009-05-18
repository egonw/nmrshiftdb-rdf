<?php header('Content-type: application/rdf+xml'); ?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:nmr="http://www.nmrshiftdb.org/onto#"
         xmlns:chem="http://www.blueobelisk.org/chemistryblogs/"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         xmlns:foaf="http://xmlns.com/foaf/0.1/"
         xmlns:owl="http://www.w3.org/2002/07/owl#"
         xmlns:bibo="http://purl.org/ontology/bibo/">

<?php 

include 'vars.php';

mysql_connect("localhost", $user, $pwd) or die(mysql_error());
# echo "<!-- Connection to the server was successful! -->\n";

mysql_select_db("nmrshiftdb") or die(mysql_error());
# echo "<!-- Database was selected! -->\n";

$molecule = $_GET["moleculeId"];

$result = mysql_query("SELECT * FROM MOLECULE WHERE MOLECULE_ID = '$molecule'");

$row = mysql_fetch_assoc($result);

if ($row) {
  $molecule = $row['MOLECULE_ID'];
  echo "<rdf:Description rdf:about=\".\">\n";
  echo "  <nmr:moleculeId>" . $molecule . "</nmr:moleculeId>\n";
  echo "  <foaf:homepage rdf:resource=\"http://nmrshiftdb.ice.mpg.de/portal/js_pane/P-Results/nmrshiftdbaction/showDetailsFromHome/molNumber/" . $molecule . "\"/>\n";
  if ($row['CAS_NUMBER']) {
    $casnum = $row['CAS_NUMBER'];
    echo "  <chem:casnumber>$casnum</chem:casnumber>\n";
    echo "  <owl:sameAs rdf:resource=\"http://bio2rdf.org/cas:$casnum\"/>\n";
  }

  $res4 = mysql_query("SELECT * FROM CANONICAL_NAME, CANONICAL_NAME_TYPE WHERE MOLECULE_ID = " .
                      "'$molecule' AND CANONICAL_NAME.CANONICAL_NAME_TYPE_ID = CANONICAL_NAME_TYPE.CANONICAL_NAME_TYPE_ID");
  while ($row4 = mysql_fetch_assoc($res4)) {
    if ($row4['CANONICAL_NAME_TYPE_ID'] == "4") {
      $inchi = trim($row4['NAME']);
      echo "<chem:inchi>$inchi</chem:inchi>\n";
      echo "<owl:sameAs rdf:resource=\"http://rdf.openmolecules.net/?$inchi\"/>\n";
    } else if ($row4['CANONICAL_NAME_TYPE_ID'] == "5") {
      echo "<chem:inchikey>" . $row4['NAME'] . "</chem:inchikey>\n";
    }
  }

  $spectraBlobs = array();

  $res2 = mysql_query("SELECT * FROM SPECTRUM, SPECTRUM_TYPE WHERE MOLECULE_ID = " .
                      "'$molecule' AND SPECTRUM_TYPE.SPECTRUM_TYPE_ID = SPECTRUM.SPECTRUM_TYPE_ID");
  while ($row2 = mysql_fetch_assoc($res2)) {
    $specId = $row2['SPECTRUM_ID'];
    echo "  <nmr:hasSpectrum rdf:resource=\"#spectrum" . $specId . "\"/>\n";
    $specBlob = "<rdf:Description rdf:about=\"#spectrum" . $specId . "\">\n";
    $specBlob = $specBlob . "  <nmr:spectrumId>" . $specId . "</nmr:spectrumId>\n";
    $specBlob = $specBlob . "  <nmr:spectrumType>" . $row2['NAME'] . "</nmr:spectrumType>\n";

    $res5 = mysql_query("SELECT * FROM SPECTRUM_LITERATURE, LITERATURE WHERE SPECTRUM_ID = " .
                      "'$specId' AND SPECTRUM_LITERATURE.LITERATURE_ID = LITERATURE.LITERATURE_ID" .
                      " AND NOT ISNULL(LITERATURE.DOI)");
    while ($row5 = mysql_fetch_assoc($res5)) {
      $specBlob = $specBlob . "  <dc:source><bibo:doi>" . $row5['DOI'] . "</bibo:doi></dc:source>\n";
    }

    $specBlob = $specBlob . "</rdf:Description>\n";
    $spectraBlobs[$specId] = $specBlob;
  }

  $res3 = mysql_query("SELECT * FROM CHEMICAL_NAME WHERE MOLECULE_ID = '$molecule'");
  while ($row3 = mysql_fetch_assoc($res3)) {
    echo "  <dc:title><![CDATA[" . $row3['NAME'] . "]]></dc:title>\n";
  }

  echo "</rdf:Description>\n";

  foreach (array_keys($spectraBlobs) as $blob) {
    echo $spectraBlobs[$blob];
  }

} else {
  echo "<!-- no information found for this molecule -->\n";
}

?>

</rdf:RDF>
