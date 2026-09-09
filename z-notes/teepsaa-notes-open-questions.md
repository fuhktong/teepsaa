# Teepsaa — Open Questions

| # | Question | Status |
|---|----------|--------|
| 1 | Which payment provider? | ✅ ABA — static QR now, PayWay API later |
| 2 | ABA PayWay API callbacks? | ✅ Yes — requires merchant account (see teepsaa-afterlaunch-payway-api.md) |
| 3 | Business registration cost in Cambodia? | ⚠️ Check moc.gov.kh or a local accountant |
| 4 | Does acting as payment intermediary require a financial license? | ⚠️ Likely yes at scale — NBC PSP license requires $500K capital. Current manual QR flow is a grey area at small scale. ABA PayWay integration (P3) resolves this by making ABA the payment holder, not teepsaa. Get a Cambodian commercial lawyer to confirm the Ministry of Commerce e-commerce permit covers the launch model. |
| 5 | Grab per-km and base fare in Phnom Penh | ✅ Resolved — real rates used: GrabBike $0.50 base + $0.225/km, GrabTuktuk $0.50 base + $0.30/km (900 KHR and 1,200 KHR respectively at 4,000 KHR/$1) |
| 6 | Should buyers be blocked from checkout if no address pin is set, or is a text address alone sufficient for the vendor to book Grab? | ⚠️ Unanswered |
| 7 | Homepage — add "Buy again" row? Shows products the buyer has ordered before. Needs buyer login, uses order history, zero new infra. | ⚠️ Deferred |
| 8 | Homepage — add "Browse by category" row? Visual category grid linking to filtered browse. Categories table already built — front-end only. | ⚠️ Deferred |
