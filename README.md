# grassrootspetition

![Screenshot](/images/screenshot.png)

(*FIXME: In one or two paragraphs, describe what the extension does and why one would download it. *)

The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Requirements

* PHP v7.2+
* CiviCRM (*FIXME: Version number*)

## Installation (Web UI)

Learn more about installing CiviCRM extensions in the [CiviCRM Sysadmin Guide](https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/).

## Installation (CLI, Zip)

Sysadmins and developers may download the `.zip` file for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
cd <extension-dir>
cv dl grassrootspetition@https://github.com/FIXME/grassrootspetition/archive/master.zip
```

## Installation (CLI, Git)

Sysadmins and developers may clone the [Git](https://en.wikipedia.org/wiki/Git) repo for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
git clone https://github.com/FIXME/grassrootspetition.git
cv en grassrootspetition
```

## Getting Started

(* FIXME: Where would a new user navigate to get started? What changes would they see? *)

## Known Issues

(* FIXME *)

## Versions

### 1.2

Public petition list changes: petitions now have a "list order" field which can be set from the manage case screen to one of: Normal|Priority|Unlisted. Public petitions are now listed: active campaigns, Priority petitions, number of signatures. Previously it was just number of signatures.

### 1.1

Brings ability for petition owners to (draft) mailings to their supporters
(requires SearchKit, Civi 5.47+), and also to download signatures. Both of
these require permissions that can be set globally, at a campaign level, or at
a petition level.
