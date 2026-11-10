# Decision support alignment ? 10 November 2026

Paper reviewed: Revised pre -oral.pdf. PDF pages 9?11 describe usage trends, demand forecasting and reorder points; pages 61?62 describe forecast recommendations. No specific forecasting equation or validated parameter values were found in these sections or the text search of the paper.

Implemented: current usable stock excludes expired quantities; saved reorder points trigger recommendations; recent Stock Out usage supports days-left estimates; historical usage and monthly trends remain visible; a new baseline projects 30-day demand using up to 90 days of recorded issues and the medicine record age. Suggested reorder points use an explicitly assumed 14-day lead time with no additional safety stock. Suggestions require staff review and do not update saved thresholds. Reports retain the shared reorder function.

The equations and 14-day lead time are implementation assumptions, not formulas attributed to the paper or approved by clinic staff. No-recorded-usage estimates remain unavailable. Record age does not establish complete observations; shortages can also suppress recorded issues. Batch-level records are not automatically grouped into products. Clinical forecasting accuracy and procurement quantities have not been validated.

Inventory history now supports inclusive date periods and pagination. Medicine records support server-side search, pagination and direct stock-movement navigation. These controls support the paper's inventory record/history functions without changing stock balances.
