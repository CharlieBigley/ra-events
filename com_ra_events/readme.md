This component has two purposes:

    Support the management and documentation of Committee Meetings for the Group or Area
    Support the management and publication of various other types of Event:
        Social Events
        Holidays/Weekends away
        Training events

Optionally it supports Bookings for nominated Events, and allows Events to be shared to other Ramblers websites
# RA Events Component

A comprehensive Joomla component for managing and publishing various types of events and meetings.

## Overview

This component has two primary purposes:

- **Committee Meetings Management** - Support the management and documentation of Committee Meetings for the Group or Area
- **Event Management** - Support the management and publication of various other types of events:
  - Social Events
  - Holidays/Weekends away
  - Training events

## Features

- Event and booking management
- Optional booking system for nominated events
- Event sharing capability to other Ramblers websites
- Committee meeting documentation 
- Customizable event types and profiles
- Integrated reporting and filtering

## Extensions

For full functionality, four extensions are provided, the first of which is required by the other three.

### **com_ra_events** (Component)
Provides rich functionality for creating and updating events of several types. Can also manage bookings for nominated events. If `com_ra_mailman` is installed, any mailing list can be used to send invitations to events. Recipients can book without logging in by following an email link.

### **mod_ra_events** (Module)
A site module showing a summary of forthcoming events. Typically displayed as "Diary dates" on the front page, allowing users to easily access full event details.

### **plg_ra_events** (Admin Plugin)
Allows remote access to events designated as "Shared events" when installed and activated.

### **plg_ra_events_cli** (Console Plugin)
Exposes a standard Joomla batch command for automated processes. Enables batch jobs to run at regular intervals, interrogating a remote website via API for "shared events" and syncing them to the client website.

## Installation

1. An installation file is available on request
2. Install via Joomla Administrator > Extensions > Extension Manager
3. Enable the component in Extension Manager
4. Configure component settings in Administrator

## Configuration

Access component settings in the Administrator panel to:
- Define event types
- Set up booking options
- Configure sharing preferences

## Requirements

- Joomla 5.x 
- RA Tools

## License

[Add license information here]

## Author

Originally created by Charlie Bigley, Area webmaster for Staffordshire