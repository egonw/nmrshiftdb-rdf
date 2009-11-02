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

$ns = "http://pele.farmbio.uu.se/nmrshiftdb/?";

mysql_connect("localhost", $user, $pwd) or die(mysql_error());
# echo "<!-- Connection to the server was successful! -->\n";

mysql_select_db("nmrshiftdb") or die(mysql_error());
# echo "<!-- Database was selected! -->\n";

$allIDs = mysql_query("SELECT DISTINCT MOLECULE_ID FROM MOLECULE ORDER BY MOLECULE_ID");

$num = mysql_numrows($allIDs);

$i=0;
while ($i < $num) {

$molecule = mysql_result($allIDs,$i,"MOLECULE_ID");

$result = mysql_query("SELECT * FROM MOLECULE WHERE MOLECULE_ID = '$molecule'");

$row = mysql_fetch_assoc($result);

if ($row) {
  $molecule = $row['MOLECULE_ID'];
  echo "<rdf:Description rdf:about=\"" . $ns . "moleculeId=$molecule\">\n";
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
  $bibBlobs = array();

  $res2 = mysql_query("SELECT * FROM SPECTRUM, SPECTRUM_TYPE WHERE ".
                      "MOLECULE_ID = '$molecule' AND " .
                      "SPECTRUM_TYPE.SPECTRUM_TYPE_ID = SPECTRUM.SPECTRUM_TYPE_ID");
  while ($row2 = mysql_fetch_assoc($res2)) {
    $specId = $row2['SPECTRUM_ID'];
    $specData = trim($row2['SPECFILE']);
    echo "  <nmr:hasSpectrum rdf:resource=\"" . $ns . "spectrumId=" . $specId . "\"/>\n";
    $specBlob = "\n<rdf:Description rdf:about=\"" . $ns . "spectrumId=" . $specId . "\">\n";
    $specBlob = $specBlob . "  <nmr:spectrumId>" . $specId . "</nmr:spectrumId>\n";
    $specBlob = $specBlob . "  <nmr:spectrumType>" . $row2['NAME'] . "</nmr:spectrumType>\n";
    $peaks = explode("|", $specData);
    for ($peakCount=0; $peakCount<count($peaks); $peakCount++) {
      $peakInfo = explode(";", $peaks[$peakCount]);
      $specBlob = $specBlob . "  <nmr:hasPeak><nmr:peak rdf:about=\"" . $ns . "peakId=s" . $specId . "p" . $peakCount . "\">\n";
      $specBlob = $specBlob . "    <nmr:hasShift rdf:datatype=\"nmr:ppm\">" . $peakInfo[0] . "</nmr:hasShift>\n";
      $specBlob = $specBlob . "  </nmr:peak></nmr:hasPeak>\n";
    }

    $res5 = mysql_query("SELECT * FROM SPECTRUM_LITERATURE, LITERATURE WHERE SPECTRUM_ID = " .
                      "'$specId' AND SPECTRUM_LITERATURE.LITERATURE_ID = LITERATURE.LITERATURE_ID" .
                      " AND LITERATURE.DOI LIKE \"10.%\"");
    while ($row5 = mysql_fetch_assoc($res5)) {
      $litId = $row5['LITERATURE_ID'];
      $doi = utf8_encode($row5['DOI']);
      if (strpos($doi, '<') == false) {
        $specBlob = $specBlob . "  <dc:source rdf:resource=\"#bib$litId\"/>\n";
        $bibBlob = "\n<rdf:Description rdf:about=\"#bib" . $litId . "\">\n";
        $bibBlob = $bibBlob . "  <bibo:doi>$doi</bibo:doi>\n";
        $bibBlob = $bibBlob . "</rdf:Description>\n";
        $bibBlobs[$litId] = $bibBlob;
      }
    }

    $res6 = mysql_query("SELECT * FROM SPECTRUM_CONDITION, `CONDITION`, CONDITION_TYPE WHERE " .
                      "SPECTRUM_CONDITION.SPECTRUM_ID = '$specId' AND " .
                      "SPECTRUM_CONDITION.CONDITION_ID = CONDITION.CONDITION_ID AND " .
                      "CONDITION.CONDITION_TYPE_ID = CONDITION_TYPE.CONDITION_TYPE_ID");
    while ($row6 = mysql_fetch_assoc($res6)) {
      $value = $row6['VALUE'];
      $type = $row6['CONDITION_NAME'];
      if ($type == "Solvent") {
        $specBlob = $specBlob . "  <nmr:solvent>$value</nmr:solvent>\n";
      }
      if ($type == "Temperature [K]") {
        $specBlob = $specBlob . "  <nmr:temperature>$value</nmr:temperature>\n";
      }
      if ($type == "Field Strength [MHz]") {
        $specBlob = $specBlob . "  <nmr:field>$value</nmr:field>\n";
      }
    }

    $specBlob = $specBlob . "</rdf:Description>\n";
    $spectraBlobs[$specId] = $specBlob;
  }

  $res3 = mysql_query("SELECT * FROM CHEMICAL_NAME WHERE MOLECULE_ID = '$molecule'");
  while ($row3 = mysql_fetch_assoc($res3)) {
    $name = utf8_encode($row3['NAME']);
    if (strpos($name, chr(0)) == false &&
        strpos($name, '<') == false &&
        strpos($name, '&') == false) {
      echo "  <dc:title>" . utf8_encode($row3['NAME']) . "</dc:title>\n";
    }
  }

  echo "</rdf:Description>\n";

  foreach (array_keys($spectraBlobs) as $blob) {
    echo $spectraBlobs[$blob];
  }

  foreach (array_keys($bibBlobs) as $blob) {
    echo $bibBlobs[$blob];
  }

} else {
  echo "<!-- no information found for this molecule -->\n";
}

$i++;

}

?>

</rdf:RDF>
