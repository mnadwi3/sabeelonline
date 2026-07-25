/**
 * Sabeel Us Salaam Online — Contact Form → Google Sheets
 *
 * SETUP
 * 1. Create a Google Spreadsheet (or open yours).
 * 2. Copy Spreadsheet ID from:
 *    https://docs.google.com/spreadsheets/d/SPREADSHEET_ID_HERE/edit
 * 3. Paste it into SPREADSHEET_ID below.
 * 4. Extensions → Apps Script → replace ALL code with this file → Save.
 * 5. Deploy → Manage deployments → Edit (pencil) → New version → Deploy
 *    Execute as: Me
 *    Who has access: Anyone
 * 6. Keep the same Web App URL in script.js (or paste a new one if Google gives it).
 *
 * This script creates/uses a tab named "Leads" with columns:
 * Timestamp | Name | Country | Phone | Email | Course | Message | Status
 */

var SPREADSHEET_ID = '1n69arRz1I7yIr8lsBCnef-Q6pMuZwQMAbPCGLnreOQc';
var SHEET_NAME = 'Leads';

var HEADERS = [
  'Timestamp',
  'Name',
  'Country',
  'Phone',
  'Email',
  'Course',
  'Message',
  'Status'
];

function doGet() {
  return jsonResponse_({
    ok: true,
    success: true,
    message: 'Sabeel contact endpoint is live.'
  });
}

function doPost(e) {
  try {
    if (!SPREADSHEET_ID || SPREADSHEET_ID.indexOf('PASTE_') === 0) {
      throw new Error('SPREADSHEET_ID is not configured in Apps Script.');
    }

    var data = parsePayload_(e);
    var sheet = getOrCreateSheet_();
    ensureHeaders_(sheet);

    var name = pick_(data, ['Name', 'name']);
    var country = pick_(data, ['Country', 'country']);
    var phone = pick_(data, ['Phone', 'phone']);
    var email = pick_(data, ['Email', 'email']);
    var course = pick_(data, ['Course', 'course']);
    var message = pick_(data, ['Message', 'message']);
    var status = pick_(data, ['Status', 'status']) || 'New Lead';
    var timestamp = pick_(data, ['Timestamp', 'timestamp']) || new Date();

    if (!name || !country || !phone || !email || !course || !message) {
      throw new Error(
        'Missing required fields. Received keys: ' + Object.keys(data).join(', ')
      );
    }

    // Write by header name so columns always line up
    var headers = sheet.getRange(1, 1, 1, HEADERS.length).getValues()[0];
    var row = headers.map(function (header) {
      switch (String(header || '').trim()) {
        case 'Timestamp':
          return timestamp instanceof Date ? timestamp : new Date(timestamp);
        case 'Name':
          return name;
        case 'Country':
          return country;
        case 'Phone':
          return phone;
        case 'Email':
          return email;
        case 'Course':
          return course;
        case 'Message':
          return message;
        case 'Status':
          return status;
        default:
          return '';
      }
    });

    sheet.appendRow(row);

    return jsonResponse_({
      ok: true,
      success: true,
      message: 'Lead saved successfully.'
    });
  } catch (err) {
    return jsonResponse_({
      ok: false,
      success: false,
      error: String(err && err.message ? err.message : err)
    });
  }
}

function parsePayload_(e) {
  // JSON body (preferred — used by the website fetch)
  var raw = e && e.postData && e.postData.contents ? e.postData.contents : '';
  if (raw) {
    try {
      var parsed = JSON.parse(raw);
      if (parsed && typeof parsed === 'object') return parsed;
    } catch (err) {
      // fall through to form fields
    }
  }

  // form-urlencoded / multipart fallback
  if (e && e.parameter && Object.keys(e.parameter).length) {
    return e.parameter;
  }

  throw new Error('Empty or invalid request body.');
}

function pick_(obj, keys) {
  for (var i = 0; i < keys.length; i++) {
    var key = keys[i];
    if (obj[key] != null && String(obj[key]).trim() !== '') {
      return clean_(obj[key]);
    }
  }
  return '';
}

function getOrCreateSheet_() {
  var ss = SpreadsheetApp.openById(SPREADSHEET_ID);
  var sheet = ss.getSheetByName(SHEET_NAME);

  // If "Leads" doesn't exist, prefer the first sheet when it looks like the contact sheet
  if (!sheet) {
    var first = ss.getSheets()[0];
    var firstHeaders = first.getLastColumn()
      ? first.getRange(1, 1, 1, Math.min(first.getLastColumn(), 8)).getValues()[0]
      : [];
    var joined = firstHeaders.join('|').toLowerCase();
    if (joined.indexOf('name') !== -1 && joined.indexOf('email') !== -1) {
      sheet = first;
      sheet.setName(SHEET_NAME);
    } else {
      sheet = ss.insertSheet(SHEET_NAME);
    }
  }

  return sheet;
}

function ensureHeaders_(sheet) {
  if (sheet.getLastRow() === 0 || sheet.getLastColumn() === 0) {
    sheet.clear();
    sheet.appendRow(HEADERS);
    sheet.getRange(1, 1, 1, HEADERS.length).setFontWeight('bold');
    sheet.setFrozenRows(1);
    return;
  }

  var width = Math.max(sheet.getLastColumn(), HEADERS.length);
  var current = sheet.getRange(1, 1, 1, width).getValues()[0];
  var matches = HEADERS.every(function (header, i) {
    return String(current[i] || '').trim() === header;
  });

  if (!matches) {
    // Fix header row so Timestamp/Name/.../Status line up
    sheet.getRange(1, 1, 1, HEADERS.length).setValues([HEADERS]);
    sheet.getRange(1, 1, 1, HEADERS.length).setFontWeight('bold');
    sheet.setFrozenRows(1);
  }
}

function clean_(value) {
  return String(value == null ? '' : value).replace(/\s+/g, ' ').trim();
}

function jsonResponse_(obj) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
