# 19 — Structured Page Editor Specification

**Status:** Product source of truth for the next page-editor implementation batches.

## 1. Purpose

The central page editor is a lightweight, responsive visual editor for ministry-owned public pages. It combines a manual editor with scoped AI assistance while preserving server-rendered output, draft-first publishing, approved content types, module gates, accessibility, and shared-hosting portability.

The editor uses a structured document:

```text
Page
└── Sections / containers
    └── Columns
        └── Elements
```

Sections and columns are editable layout objects. Elements are editable content objects. No element may sit directly on the page outside a section and column.

## 2. Design principles

- The canvas is the primary workspace; controls remain compact and contextual.
- Selecting an object makes its hierarchy clear: Page, Section, Column, or Element.
- Every setting has a safe default and an explicit responsive behavior.
- Desktop, tablet, and mobile values can be independently overridden.
- The editor never accepts arbitrary HTML, CSS, JavaScript, or iframe markup.
- The public renderer uses an approved recursive renderer, not editor-generated markup.
- Ministry branding uses shared semantic theme tokens. Editor controls remain neutral unless a ministry-selected accent is required for an action state.
- Manual editing remains complete when AI is unavailable.
- Every AI proposal is reviewable, scoped, undoable, and separate from saving or publishing.

## 3. Page document contract

The stored page payload is versioned. The first structured format is `version: 2`.

```json
{
  "version": 2,
  "sections": [
    {
      "id": "section-uuid",
      "type": "section",
      "settings": {},
      "responsive": {},
      "columns": []
    }
  ]
}
```

Every section, column, and element has a stable editor ID. IDs are for document references and UI state; they do not grant access or become public identifiers.

## 4. Section/container settings

Selecting a section opens the section settings panel. Settings are grouped into Layout, Style, Spacing, Background, Responsive, and Advanced.

### 4.1 Layout

- Content width: full, wide, standard, narrow, custom.
- Inner content max width.
- Column count and column template.
- Column gap.
- Horizontal alignment: left, center, right, stretch.
- Vertical alignment: top, center, bottom, stretch.
- Section minimum height: auto, viewport, custom.
- Content overflow: visible, hidden.
- Reverse column order on mobile.

### 4.2 Style

- Background color using a theme token or approved color value.
- Border style: none, solid, dashed, dotted.
- Border width per side or linked value.
- Border color using a theme token or approved color value.
- Border radius per corner or linked value.
- Box shadow from a restrained preset.
- Opacity where it does not reduce content readability.

### 4.3 Spacing

- Padding for top, right, bottom, and left.
- Margin for top, right, bottom, and left.
- Linked/unlinked spacing controls.
- Separate desktop, tablet, and mobile values.
- Safe limits to prevent unusable negative space or layout overflow.

### 4.4 Background media

- Background image selected from the ministry media library.
- Background video only when the approved media policy supports it.
- Position: left, center, right and top, center, bottom.
- Size: cover, contain, auto.
- Repeat: none, repeat, repeat-x, repeat-y.
- Fixed or scrolling attachment only where supported accessibly.
- Overlay color and overlay opacity.
- Readability warning when contrast is insufficient.

### 4.5 Responsive section behavior

- Visibility per breakpoint: visible or hidden.
- Section width per breakpoint.
- Padding and margin per breakpoint.
- Column count per breakpoint.
- Column gap per breakpoint.
- Alignment per breakpoint.
- Background position and size per breakpoint where needed.

## 5. Column settings

Selecting a column opens the column settings panel without opening the section settings.

### 5.1 Layout

- Width as a percentage, fraction, or preset.
- Minimum width.
- Maximum width.
- Column content alignment: left, center, right, stretch.
- Vertical alignment: top, center, bottom, stretch.
- Child element gap.
- Wrap behavior.

### 5.2 Style

- Background color.
- Border and border radius.
- Box shadow.
- Minimum height.
- Overflow behavior.

### 5.3 Responsive behavior

- Width at desktop, tablet, and mobile.
- Stack or remain inline at each breakpoint.
- Order at each breakpoint.
- Visibility at each breakpoint.
- Alignment at each breakpoint.
- Child gap at each breakpoint.

## 6. Element contract

Elements are placed inside columns. The first supported groups are:

- Text: heading, paragraph, quote, list, accordion.
- Actions: button, call to action.
- Media: image, gallery, video, audio, file download, media and text.
- Ministry content: events, service times, groups, locations, giving, prayer, next steps, announcements, volunteer, sermon spotlight, books, and newsletter.
- Layout helpers: divider and spacer.

Each element owns its content, accessibility fields, visibility, alignment, spacing, and approved style settings. An element cannot change its parent section or column settings through its own content form.

## 7. Responsive model

The editor uses three named breakpoints:

- Desktop.
- Tablet.
- Mobile.

Values inherit downward unless an explicit override exists. For example, a desktop padding value applies to tablet and mobile until those values are changed.

The UI must show whether a value is inherited or overridden. Resetting an override returns the value to inheritance rather than copying a hardcoded value.

The renderer must use CSS variables and semantic classes rather than generating arbitrary inline CSS from user input.

## 8. Editor interaction model

### 8.1 Canvas selection

- Clicking a section selects the section.
- Clicking a column selects the column.
- Clicking an element selects the element.
- Selection outlines identify the selected level and its parent path.
- The breadcrumb shows Page / Section / Column / Element.
- Add controls appear only at the selected hierarchy level.

### 8.2 Structure operations

Sections:

- Add section.
- Duplicate section.
- Reorder section.
- Delete section.
- Change section layout.

Columns:

- Add column.
- Duplicate column.
- Reorder column.
- Delete column.
- Move an element into another column.

Elements:

- Add element.
- Duplicate element.
- Reorder element.
- Move element within or between columns.
- Delete element.

Destructive operations require undo support and a clear confirmation when multiple children will be removed.

### 8.3 Inspector

The inspector has two tabs for the selected object:

- Manual: complete settings for that object.
- AI assist: a scoped request that may propose changes only to the selected object.

The inspector never displays unrelated controls from another hierarchy level.

## 9. AI behavior

Page planning may propose sections, columns, and approved elements. It cannot invent media URLs, destinations, ministry facts, arbitrary HTML, CSS, or scripts.

Section AI receives the page purpose, neighboring sections, the selected section, and the current theme context. It may change only the selected section after staff approval.

Column AI receives the selected column and its surrounding section context. It may change only the selected column layout or its approved child arrangement.

Element AI receives the selected element and page context. It may change only the selected element’s content and approved settings.

Applying a proposal creates an undo point. Applying is not saving, and saving is not publishing.

## 10. Validation and security

The server validates the complete document recursively:

- Supported document version.
- Maximum sections, columns, elements, and nesting depth.
- Approved object and element types.
- Scalar content limits.
- Responsive values and spacing bounds.
- Approved media ownership and publication state.
- Element/media type compatibility.
- Safe internal and HTTP(S) destinations.
- Module and permission availability.
- No arbitrary markup, scripts, stylesheets, or unsafe URLs.

Disabled modules remain hidden and server-blocked even if a stale document contains their elements.

## 11. Legacy migration

Existing flat documents remain readable. The migration converter maps:

- A top-level element into a section with one column.
- A current section block into a section with generated columns and child elements.
- A media-and-text block into a two-column section.
- Existing saved content into equivalent structured objects without inventing new content.

The original document remains available in the page revision history. Migration is repeatable and must not duplicate sections.

## 12. Performance and portability

- Keep the editor client-side interaction lightweight and route-scoped.
- Do not load the full media catalog until the media picker opens.
- Use event delegation and clean up observers on navigation.
- Debounce preview updates where text input is continuous.
- Keep server-rendered form fallback available.
- Avoid a required Node.js, Redis, or realtime dependency for the editor.
- Public rendering must remain fast when editor scripts are not loaded.

## 13. Implementation batches

1. **Document foundation:** version 2 contract, recursive validation, legacy conversion, and revision compatibility.
2. **Recursive renderer:** sections, columns, child elements, responsive CSS variables, and public output.
3. **Manual structure editor:** hierarchy selection, add/move/duplicate/delete, and section/column settings.
4. **Responsive controls:** breakpoint overrides, inheritance, stacking, ordering, spacing, and visibility.
5. **Element library:** complete approved element settings and media compatibility.
6. **AI integration:** page planning, section AI, column AI, and element AI with scoped review.
7. **Migration and quality gate:** convert existing pages, browser review, accessibility review, performance review, and documentation/status update.
