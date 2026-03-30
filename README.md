# IPSymcon-AdvancedShutterControl

An IP-Symcon module for advanced control of roller shutters and blinds. Supports group control, weekly schedules, per-shutter position scaling, and manual operation lock.

## Features

- **Group Control** – Control multiple roller shutters / blinds as a group with a single master position slider
- **Operating Modes** – Open, Close, Shade, and Ventilate modes with configurable positions
- **Per-Shutter Scaling** – Each shutter has its own open/closed value (any range), allowing mixed actuator types in one group
- **Weekly Schedule** – Automated position changes via IP-Symcon's built-in schedule events (per-day configuration)
- **Manual Operation Lock** – Optionally block manual changes on the physical shutters
- **Localized** – English and German translations included

## Requirements

- IP-Symcon 8.1 or higher

## Installation

1. Open the IP-Symcon Management Console
2. Navigate to **Modules** in the object tree
3. Click **Add Module** and enter the repository URL:
   ```
   https://github.com/mwlf01/IPSymcon-AdvancedShutterControl
   ```
4. Click **OK** and wait for the module to be installed

## Configuration

### Shutters

Add the position variables of your roller shutters / blinds. These must be **Integer** or **Float** variables. Each shutter has its own open and closed value, allowing mixed actuator types and ranges in one group. The module scales linearly between the open and close values. For Integer variables the result is rounded to whole numbers; for Float variables you can configure the number of decimal places.

| Property | Default | Description |
|----------|---------|-------------|
| Shutter Variable | – | The position variable of the shutter actuator |
| Name | – | Optional display name |
| Open Value | 0 | Actuator value that means fully open (per shutter) |
| Close Value | 100 | Actuator value that means fully closed (per shutter) |
| Decimal Places | 0 | Number of decimal places for Float variables (0–6) |

### Weekly Schedule

The module creates a weekly schedule event with four actions:

| Action | Color | Description |
|--------|-------|-------------|
| Open | Green | Move shutters to the open position |
| Closed | Grey | Move shutters to the closed position |
| Shade | Orange | Move shutters to the shade position |
| Ventilate | Blue | Move shutters to the ventilate position |

## Variables

The following variables are created automatically and can be used for visualization or automation:

| Ident | Name | Type | Description |
|-------|------|------|-------------|
| TargetPosition | Target Position | Integer | Master position slider (0 = open, 100 = closed). The module scales this to each shutter's individual value range. |
| ShutterMode | Shutter Mode | Integer | Current mode: Open (0), Closed (1), Shade (2), Ventilate (3) |
| ShadePosition | Shade Position | Integer | Logical position used for shade mode (0–100) |
| VentilatePosition | Ventilate Position | Integer | Logical position used for ventilate mode (0–100) |
| ManualOperationBlocked | Manual Operation Blocked | Boolean | Block manual changes on physical shutters |

## Public Functions

### Position Control

```php
// Set the target position for all shutters (0-100%)
ASC_SetTargetPosition(int $InstanceID, int $Position);

// Apply the current target position to all shutters
ASC_ApplyPosition(int $InstanceID);

// Get the current target position
int ASC_GetTargetPosition(int $InstanceID);
```

### Mode Control

```php
// Set the shutter mode (0=Open, 1=Close, 2=Shade, 3=Ventilate)
ASC_SetShutterMode(int $InstanceID, int $Mode);

// Convenience functions
ASC_SetOpen(int $InstanceID);
ASC_SetClose(int $InstanceID);
ASC_SetShade(int $InstanceID);
ASC_SetVentilate(int $InstanceID);
```

### Schedule

```php
// Get the schedule event ID
int ASC_GetScheduleEventID(int $InstanceID);

// Called by the schedule (internal use)
ASC_ScheduleAction(int $InstanceID, int $ActionID);
```

## Support

For issues, feature requests, or contributions, please visit:
- [GitHub Repository](https://github.com/mwlf01/IPSymcon-AdvancedShutterControl)
- [GitHub Issues](https://github.com/mwlf01/IPSymcon-AdvancedShutterControl/issues)
- [Symcon Community](https://community.symcon.de/) – User: **mwlf**

---

## License

MIT License – see [LICENSE](LICENSE) for details.

---

## Author

**mwlf01**

- GitHub: [@mwlf01](https://github.com/mwlf01)
- Symcon Community: [mwlf](https://community.symcon.de/)
