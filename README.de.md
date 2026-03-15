# IPSymcon-AdvancedShutterControl

Ein IP-Symcon-Modul zur erweiterten Steuerung von Rollladen und Jalousien. Unterstützt Gruppensteuerung, Wochenpläne, individuelle Positionsskalierung pro Rollladen und manuelle Bediensperre.

## Funktionen

- **Gruppensteuerung** – Steuern Sie mehrere Rollladen / Jalousien als Gruppe mit einem einzigen Master-Positionsregler
- **Betriebsmodi** – Offen, Geschlossen, Beschattung und Lüften mit konfigurierbaren Positionen
- **Individuelle Positionsskalierung** – Jeder Rollladen hat seinen eigenen Offen-/Geschlossen-Positionsbereich, sodass verschiedene Aktortypen in einer Gruppe gemischt werden können
- **Wochenplan** – Automatische Positionsänderungen über die integrierten Zeitplan-Ereignisse von IP-Symcon (Konfiguration pro Tag)
- **Manuelle Bediensperre** – Optionale Sperrung manueller Änderungen an den physischen Rollladen
- **Lokalisiert** – Englische und deutsche Übersetzungen enthalten

## Voraussetzungen

- IP-Symcon 8.1 oder höher

## Installation

1. Öffnen Sie die IP-Symcon Verwaltungskonsole
2. Navigieren Sie zu **Module** im Objektbaum
3. Klicken Sie auf **Modul hinzufügen** und geben Sie die Repository-URL ein:
   ```
   https://github.com/mwlf01/IPSymcon-AdvancedShutterControl
   ```
4. Klicken Sie auf **OK** und warten Sie, bis das Modul installiert ist

## Konfiguration

### Rollladen

Fügen Sie die Positionsvariablen Ihrer Rollladen / Jalousien hinzu. Dies müssen **Integer**- oder **Float**-Variablen mit einem Bereich von 0–100% sein. Jeder Rollladen hat seine eigene Offen- und Geschlossen-Position, sodass verschiedene Aktortypen in einer Gruppe gemischt werden können.

| Eigenschaft | Standard | Beschreibung |
|-------------|---------|-------------|
| Rollladen-Variable | – | Die Positionsvariable des Rollladenaktors |
| Name | – | Optionaler Anzeigename |
| Offen-Position (%) | 0 | Positionswert für vollständig geöffnet (pro Rollladen) |
| Geschlossen-Position (%) | 100 | Positionswert für vollständig geschlossen (pro Rollladen) |

### Wochenplan

Das Modul erstellt ein Wochenplan-Ereignis mit vier Aktionen:

| Aktion | Farbe | Beschreibung |
|--------|-------|-------------|
| Offen | Grün | Rollladen in die Offen-Position fahren |
| Geschlossen | Grau | Rollladen in die Geschlossen-Position fahren |
| Beschattung | Orange | Rollladen in die Beschattungsposition fahren |
| Lüften | Blau | Rollladen in die Lüftungsposition fahren |

## Variablen

Die folgenden Variablen werden automatisch erstellt und können zur Visualisierung oder Automatisierung verwendet werden:

| Ident | Name | Typ | Beschreibung |
|-------|------|-----|-------------|
| TargetPosition | Sollposition | Integer | Master-Positionsregler (0–100%) |
| ShutterMode | Rollladen-Modus | Integer | Aktueller Modus: Offen (0), Geschlossen (1), Beschattung (2), Lüften (3) |
| ShadePosition | Beschattungsposition | Integer | Position für den Beschattungsmodus (0–100%) |
| VentilatePosition | Lüftungsposition | Integer | Position für den Lüftungsmodus (0–100%) |
| ManualOperationBlocked | Manuelle Bedienung gesperrt | Boolean | Manuelle Änderungen an physischen Rollladen sperren |

## Öffentliche Funktionen

### Positionssteuerung

```php
// Sollposition für alle Rollladen setzen (0-100%)
ASC_SetTargetPosition(int $InstanceID, int $Position);

// Aktuelle Sollposition auf alle Rollladen anwenden
ASC_ApplyPosition(int $InstanceID);

// Aktuelle Sollposition abfragen
int ASC_GetTargetPosition(int $InstanceID);
```

### Modussteuerung

```php
// Rollladen-Modus setzen (0=Offen, 1=Geschlossen, 2=Beschattung, 3=Lüften)
ASC_SetShutterMode(int $InstanceID, int $Mode);

// Komfortfunktionen
ASC_SetOpen(int $InstanceID);
ASC_SetClose(int $InstanceID);
ASC_SetShade(int $InstanceID);
ASC_SetVentilate(int $InstanceID);
```

### Wochenplan

```php
// Zeitplan-Ereignis-ID abfragen
int ASC_GetScheduleEventID(int $InstanceID);

// Wird vom Zeitplan aufgerufen (interne Verwendung)
ASC_ScheduleAction(int $InstanceID, int $ActionID);
```

## Lizenz

MIT-Lizenz – siehe [LICENSE](LICENSE) für Details.
