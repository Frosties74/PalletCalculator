# PalletCalculator

PlentyONE Plugin zur dynamischen Berechnung
des Palettenbedarfs eines Auftrags.

## Unterstützte Fälle

Das Plugin unterstützt:

- normale Varianten
- Bundle-Komponenten
- gemischte Aufträge
- nicht palettenrelevante Artikel
- mehrere Bundles in einem Auftrag

## Grundregel

Standard:

45 palettenrelevante Stück = 1 Palette

Berechnung:

ceil(Palettenmenge / 45)

Beispiele:

1 - 45 = 1 Palette

46 - 90 = 2 Paletten

91 - 135 = 3 Paletten

136 - 180 = 4 Paletten

usw.


## Palettenrelevante Varianten

In PlentyONE muss eine Eigenschaft angelegt werden:

Palettenrelevant

Diese Eigenschaft wird allen Varianten zugewiesen,
die bei der Palettenberechnung berücksichtigt
werden sollen.

Entscheidend ist nur, ob die Eigenschaft an der
Variante vorhanden ist.

Die ID dieser Eigenschaft wird in der
Plugin-Konfiguration hinterlegt.


## Bundle-Unterstützung

Bundle-Köpfe werden NICHT gezählt.

Stattdessen werden die Bundle-Komponenten
(typeId = 3) berücksichtigt.

Beispiel:

1 x Bundle A

Bundle A enthält:

40 x Sack Produkt A

Zusätzlich:

20 x Sack Produkt B

Produkt A und Produkt B besitzen beide die
Eigenschaft "Palettenrelevant".

Berechnung:

40 + 20 = 60

60 / 45 = 1,333

Aufgerundet:

2 Paletten


## Nicht palettenrelevante Ware

Beispiel:

1 x Bundle A = 40 Sack Produkt A

20 x Produkt B

5 x Reiniger

2 x Zubehör

Reiniger und Zubehör besitzen die Eigenschaft
"Palettenrelevant" nicht.

Berechnung:

40 + 20 = 60

Nicht:

40 + 20 + 5 + 2


Ergebnis:

2 Paletten


## Auftragseigenschaft

Es muss zusätzlich ein Order-Property-Typ
angelegt werden:

Palettenanzahl

Empfohlener Datentyp:

int

Die ID dieses Property-Typs muss in der
Plugin-Konfiguration eingetragen werden.


## Plugin-Konfiguration

Nach der Installation des Plugins:

Plugins
→ Plugin-Set
→ PalletCalculator
→ Konfiguration

Folgende Werte eintragen:

1. Eigenschafts-ID "Palettenrelevant"

2. Auftragseigenschafts-ID "Palettenanzahl"

3. Stück pro Palette

Standard:

45

4. Maximalgewicht der Palette (kg)

Für jede palettenrelevante Variante muss in PlentyONE ein
Bruttogewicht hinterlegt sein. Nicht palettenrelevante Positionen
werden auch bei der Gewichtsberechnung ignoriert.


## PlentyFlow

Nach erfolgreichem Deployment steht in PlentyFlow
eine Plugin-Aktion zur Verfügung:

Paletten berechnen

Diese Aktion:

1. lädt den Auftrag

2. liest alle Auftragspositionen

3. ignoriert Bundle-Köpfe

4. berücksichtigt normale Varianten

5. berücksichtigt Bundle-Komponenten

6. prüft die Eigenschaft "Palettenrelevant"

7. addiert ausschließlich relevante Mengen und Gewichte

8. berechnet die Palettenanzahl nach Stückzahl und Gewicht

9. speichert das Ergebnis am Auftrag

10. übergibt den Auftrag an den nächsten
    PlentyFlow-Schritt


## Beispiel PlentyFlow

Trigger
|
v
Paletten berechnen
|
v
Palettenanzahl am Auftrag vorhanden
|
v
weitere Versandlogik


## Formel

Paletten nach Stückzahl = ceil(
    Palettenrelevante Menge / Stück pro Palette
)

Paletten nach Gewicht = ceil(
    Gesamtgewicht palettenrelevanter Positionen
    /
    Maximalgewicht der Palette
)

Palettenanzahl = max(
    Paletten nach Stückzahl,
    Paletten nach Gewicht
)


## Version

1.1.1
