# Brief: Calendar Task List and Editing

## Original Request
In `CalendarView`, make the task list visible so users can edit tasks from the calendar.

## Objective
Make existing tasks easy to discover and open for editing without removing calendar day or member filtering.

## Proposed Solution Approach
Show all tasks when CalendarView first opens, retain the day filters for narrowing the list, and keep opening the task editor when a task is selected. When creating a task from the all-days view, use a concrete day as the form default.

## Scope
### In Scope
- Make the complete task list visible by default in CalendarView.
- Preserve day and member filtering and the existing task editing modal.
- Preserve a valid day default when adding tasks from the all-days view.

### Out of Scope
- Changes to backend task persistence or other screens.
- Calendar redesign.

## Assumptions
- The existing task card click and `TaskModal` edit flow are the intended edit interaction.
- “Visualizar el listado” means seeing tasks across all days without first selecting each day.

## Open Questions / Missing Information
- None blocking; the current week remains represented by the existing day selector.

## Next Step
Implement the all-days initial filter and verify the frontend build.
