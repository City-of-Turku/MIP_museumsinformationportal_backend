<!-- Labels and translations for the field names. All publishable fields need to be listed here. -->
<#assign translations = {
    "kiinteistotunnus": "Kiinteistötunnus (Fastighetsbeteckning)",
    "nimi": "Nimi (Namn)",
    "kiinteiston_sijainti": "Sijainti (Läge)",
    "kunta": "Kunta (Kommun)",
    "kyla": "Kylä (By)",
    "yhteenveto": "Yhteenveto (Sammanfattning)",
    "arvoluokka": "Arvoluokka (Värdeklassificering)",
    "kulttuurihistorialliset_arvot": "Kulttuurihistorialliset arvot (Kulturhistoriska värden)",
    "paikkakunta": "Paikkakunta (Ort)",
    "inventointiprojekti": "Inventointiprojekti (Inventeringsprojekt)",
    "inventoija": "Inventoija (Inventerare)",
    "inventointipaiva": "Inventointipäivä (Inventeringsdatum)",
    "aluetyypit": "Aluetyypit (Områdestyper)",
    "tilatyypit": "Historialliset tilatyypit (Historiska lokaltyper)",
    "asutushistoria": "Asutushistoria (Bosättningshistoria)",
    "lahiymparisto": "Lähiympäristö (Närmiljö)",
    "pihapiiri": "Pihapiiri (Gårdsplan)",
    "arkeologinen_intressi": "Arkeologinen intressi (Arkeologiskt intresse)",
    "muu_historia": "Muu historia (Övrig historia)",
    "perustelut": "Perustelut (Motiveringar)",
    "kiinteisto": "Kiinteistö (Fastighet)",
    "osoitteet": "Osoitteet (Adresser)",
    "postinumero": "Postinumero (Postnummer)",
    "inventointinumero": "Inventointinumero (Inventeringsnummer)",
    "rakennustunnus": "Rakennustunnus (Byggnadsbeteckning)",
    "rakennustyypit": "Rakennustyypit (Byggnadstyper)",
    "rakennustyyppi_kuvaus": "Rakennustyypin kuvaus (Beskrivning av byggnadstyper)",
    "rakennusvuosi_alku": "Rakennusvuosi alku (Början av byggåret)",
    "rakennusvuosi_loppu": "Rakennusvuosi loppu (Slutet av byggåret)",
    "rakennusvuosi_selite": "Byggnadsår - förklaring",
    "muutosvuodet": "Muutosvuodet (Ändringsår)",
    "alkuperainen_kaytto": "Alkuperäinen käyttö (Ursprunglig användning)",
    "nykykaytto": "Nykykäyttö (Nuvarande användning)",
    "kerroslukumaara": "Kerroslukumäärä (Antal våningar)",
    "asuin_ja_liikehuoneistoja": "Asuin- ja liikehuoneistoja (Bostäder och affärslokaler)",
    "perustus": "Perustus (Grund)",
    "runko": "Runko (Stomme)",
    "julkisivumateriaali": "Julkisivumateriaali (Fasadmaterial)",
    "ulkovari": "Ulkoväri (Ytterfärg)",
    "kattotyypit": "Kattotyypit (Tak)",
    "katetyypit": "Katetyypit (Täckning)",
    "kunto": "Kunto (Skick)",
    "nykytyyli": "Nykytyyli (Nuvarande stil)",
    "purettu": "Purettu (Riven)",
    "erityispiirteet": "Erityispiirteet (Särdrag)",
    "kulttuurihistoriallisetarvot_perustelut": "Perustelut (Motiveringar)",
    "rakennuksen_sijainti": "Sijainti (Läge)",
    "suunnittelija": "Suunnittelijat (Planerare)",
    "rakennushistoria": "Rakennushistoria (Byggnadshistoria)",
    "sisatilakuvaus": "Sisätilakuvaus (Beskrivning av interiören)",
    "muut_tiedot": "Muut tiedot (Övriga uppgifter)",
    "suojelutiedot": "Suojelutiedot (Skyddsuppgifter)",
    "historia": "Historia (Historia)",
    "maisema": "Maisema (Landskap)",
    "nykytila": "Nykytila (Nuläge)",
    "kuntakyla": "Kunta, kylä (Kommun, by)",
    "alue": "Alue (Områd)",
    "aluetyyppi": "Aluetyyppi (Områdestyp)",
    "linkit_paikallismuseoihin": " ",
    "paikallismuseot_kuvaus": ""
}>

<#list features as feature>
    <!-- Show the image first, if it is present -->
    <#list feature.attributes as attribute>
        <#if attribute.name == 'kuva_url' && attribute.value != ''>
            <img src="${attribute.value}" width="400"></img>
            <br>
        </#if>
    </#list>
    <!-- Loop rest of the attributes -->
    <dl>
        <#list feature.attributes as attribute>
            <#if !attribute.isGeometry>
                <#if attribute.name != 'id'>
                    <#if attribute.name != 'kuva_url'>
                        <#assign attrName = attribute.name>
                        <#assign attrValue = attribute.value>
                        <#-- Add swedish translation in parentheses to the value if such exists -->
                        <#list feature.attributes as attr>
                            <#if attr.name == attrName + "_se">
                                <#if attr.value != "">
                                    <#assign attrValue = attrValue + " (" + attr.value + ")">
                                </#if>
                            </#if>
                        </#list>
                        <#-- Replace empty values with dash, except "Paikallismuseot_kuvaus" field -->
                        <#if attrValue == "">
                            <#if attrName != 'paikallismuseot_kuvaus'>
                                <#assign attrValue = "-">
                            </#if>
                        </#if>
                        <#-- Replace true / false with Kyllä / Ei -->
                        <#if attrValue == "true">
                            <#assign attrValue = "Kyllä (Ja)">
                        <#elseif attrValue == "false">
                            <#assign attrValue = "Ei (Nej)">
                        </#if>
                        <!-- Replace the field name with the label and translation -->
                        <#if translations[attrName]??>
                            <#assign attrName = translations[attrName]>
                        </#if>
                        <#if !attribute.name?contains("_se")>
                            <dt style="margin-bottom:0.1em; font-family:verdana;">${attrName}</dt>
                            <!-- Linkit_paikallismuseoihin needs special formatting, as it needs to be split by the line break -->
                            <!-- and then for the _target=blank to work correctly, we need to verify & add http:// to the value -->
                            <#if attrName == translations["linkit_paikallismuseoihin"]>
                                <#if attrValue != "-">
                                    <#list attrValue?split("\n") as row>
                                        <#assign titleEndIndex = row?index_of(":")>
                                        <#assign rowLength = row?length>
                                        <#assign title = row?substring(0,titleEndIndex)>
                                        <#assign url = row?substring(titleEndIndex+1, rowLength)>
                                        <dd><a style="margin-bottom:0.1em; font-family:verdana;" href="${url}" target="_blank">${title}</a></dd>
                                    </#list>
                                <#else>
                                    <dd style="margin-bottom:0.3em; font-family:verdana;">${attrValue}</dd>
                                </#if>
                            <#else>
                                <dd style="margin-bottom:0.3em; font-family:verdana;">${attrValue}</dd>
                            </#if>
                        </#if>
                    </#if>
                </#if>
            </#if>
        </#list>
    </dl>
    <hr width="400">
    <br>
</#list>
