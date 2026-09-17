# Infection MSI — CI run 35082943676 (commit 29cb645)

Date du rapport : 2026-09-17 (analyse). Commit : `29cb6458027097f43a27ecd4fd5c5f551539aef9`.

## Résultat CI

```
Total: 3741

Killed by Test Framework: 2872
Killed by Static Analysis: 0
Errored: 0
Syntax Errors: 0
Escaped: 869
Timed Out: 0
Skipped: 0
Ignored: 0
Not Covered: 0
```

MSI = 2872 / 3741 = **76.77 %** (< 80 % requis par `infection.json` → CI rouge).
Escaped : **869**. Pour passer, il faut escaped ≤ 748 (20 % de 3741), soit **≥ 121 mutants à tuer**.

## Mutants échappés par fichier (869 au total)

| Fichier | Escaped |
|---|---:|
| src/…/NotificationService.php | 82 |
| src/…/StatsQueryRepository.php | 72 |
| src/…/CronService.php | 71 |
| src/…/ReportQueryRepository.php | 54 |
| src/…/ReportLifecycleRepository.php | 52 |
| src/…/SessionService.php | 49 |
| src/…/CustomFieldsService.php | 46 |
| src/…/ConfigService.php | 37 |
| src/…/FormattingService.php | 32 |
| src/…/ReportService.php | 30 |
| src/…/UserRepository.php | 26 |
| src/…/EmailOutboxRepository.php | 24 |
| src/…/ReportWriteRepository.php | 22 |
| src/…/AuditRepository.php | 20 |
| src/…/AssetService.php | 18 |
| src/…/RegistryRepository.php | 17 |
| src/…/RegistryCardService.php | 16 |
| src/…/RegistryFieldRepository.php | 15 |
| src/…/ReportResponseRepository.php | 15 |
| src/…/ExportService.php | 14 |
| src/…/StatsRepository.php | 13 |
| src/…/ReportAgentRepository.php | 12 |
| src/…/ReportRepository.php | 12 |
| src/…/CookieService.php | 12 |
| src/…/ConfigRepository.php | 10 |
| src/…/CryptoService.php | 9 |
| src/…/HttpService.php | 8 |
| src/…/RegistryPolicy.php | 8 |
| src/…/SessionTokenService.php | 8 |
| src/…/NotificationRepository.php | 7 |
| src/…/SQLiteSessionHandler.php | 7 |
| src/…/SessionRepository.php | 6 |
| src/…/SiteRepository.php | 6 |
| src/…/RegistryFieldValueRepository.php | 5 |
| src/…/AccessService.php | 5 |
| src/…/UserService.php | 5 |
| src/…/AuthService.php | 4 |
| src/…/OutboxHealthService.php | 4 |
| src/…/TransactionManager.php | 3 |
| src/…/ReopenReportCommand.php | 2 |
| src/…/ReportAuditRepository.php | 2 |
| src/…/Router.php | 2 |
| src/…/SessionManager.php | 2 |
| src/…/CreateReportCommand.php | 1 |
| src/…/UpdateReportCommand.php | 1 |
| src/…/OutboxEvent.php | 1 |
| src/…/SessionDataService.php | 1 |
| src/…/StatisticsService.php | 1 |

## Liste exhaustive (fichier:ligne mutateur)

### NotificationService.php (82)

- 52:`MethodCallRemoval`
- 65:`MethodCallRemoval`
- 96:`Assignment`
- 97:`Concat`
- 97:`ConcatOperandRemoval`
- 97:`ConcatOperandRemoval`
- 97:`Concat`
- 97:`ConcatOperandRemoval`
- 97:`Assignment`
- 98:`Assignment`
- 99:`Concat`
- 99:`ConcatOperandRemoval`
- 99:`Concat`
- 99:`ConcatOperandRemoval`
- 99:`Assignment`
- 100:`Concat`
- 100:`ConcatOperandRemoval`
- 100:`ConcatOperandRemoval`
- 100:`Concat`
- 100:`ConcatOperandRemoval`
- 100:`ConcatOperandRemoval`
- 100:`Concat`
- 100:`ConcatOperandRemoval`
- 100:`Concat`
- 100:`ConcatOperandRemoval`
- 101:`ArrayItemRemoval`
- 101:`Concat`
- 101:`ConcatOperandRemoval`
- 101:`ConcatOperandRemoval`
- 101:`Concat`
- 101:`ConcatOperandRemoval`
- 107:`MethodCallRemoval`
- 129:`LogicalAnd`
- 130:`Concat`
- 130:`ConcatOperandRemoval`
- 130:`Concat`
- 130:`ConcatOperandRemoval`
- 137:`LogicalAnd`
- 137:`LogicalAnd`
- 140:`Assignment`
- 141:`Concat`
- 141:`ConcatOperandRemoval`
- 141:`ConcatOperandRemoval`
- 141:`Concat`
- 141:`ConcatOperandRemoval`
- 141:`Assignment`
- 142:`Assignment`
- 143:`ArrayItemRemoval`
- 143:`Concat`
- 143:`ConcatOperandRemoval`
- 143:`ConcatOperandRemoval`
- 143:`Concat`
- 143:`ConcatOperandRemoval`
- 158:`Coalesce`
- 158:`LogicalAnd`
- 161:`Assignment`
- 162:`Concat`
- 162:`ConcatOperandRemoval`
- 162:`ConcatOperandRemoval`
- 162:`Concat`
- 162:`ConcatOperandRemoval`
- 162:`Assignment`
- 163:`Concat`
- 163:`ConcatOperandRemoval`
- 163:`ConcatOperandRemoval`
- 163:`Concat`
- 163:`ConcatOperandRemoval`
- 163:`Assignment`
- 164:`Assignment`
- 165:`ArrayItemRemoval`
- 165:`Concat`
- 165:`ConcatOperandRemoval`
- 165:`ConcatOperandRemoval`
- 165:`Concat`
- 165:`ConcatOperandRemoval`
- 170:`Concat`
- 170:`ConcatOperandRemoval`
- 170:`ConcatOperandRemoval`
- 170:`Concat`
- 170:`ConcatOperandRemoval`
- 177:`MethodCallRemoval`
- 191:`MethodCallRemoval`

### StatsQueryRepository.php (72)

- 55:`DecrementInteger`
- 55:`IncrementInteger`
- 73:`ConcatOperandRemoval`
- 73:`ConcatOperandRemoval`
- 95:`DecrementInteger`
- 95:`IncrementInteger`
- 96:`DecrementInteger`
- 96:`IncrementInteger`
- 97:`DecrementInteger`
- 97:`IncrementInteger`
- 98:`DecrementInteger`
- 98:`IncrementInteger`
- 99:`DecrementInteger`
- 99:`IncrementInteger`
- 118:`PublicVisibility`
- 121:`Ternary`
- 123:`Foreach_`
- 125:`NotIdentical`
- 125:`LogicalAnd`
- 125:`LogicalAndAllSubExprNegation`
- 125:`LogicalAndNegation`
- 125:`LogicalAndSingleSubExprNegation`
- 129:`ArrayOneItem`
- 166:`UnwrapArrayFlip`
- 167:`UnwrapArrayFlip`
- 171:`PregMatchRemoveCaret`
- 171:`PregMatchRemoveDollar`
- 171:`LogicalOr`
- 171:`LogicalOr`
- 175:`LogicalAndAllSubExprNegation`
- 176:`LogicalNot`
- 177:`Continue_`
- 182:`ConcatOperandRemoval`
- 183:`ConcatOperandRemoval`
- 269:`DecrementInteger`
- 269:`IncrementInteger`
- 285:`Concat`
- 285:`ConcatOperandRemoval`
- 285:`DecrementInteger`
- 285:`ConcatOperandRemoval`
- 306:`ConcatOperandRemoval`
- 307:`ConcatOperandRemoval`
- 333:`DecrementInteger`
- 333:`IncrementInteger`
- 337:`DecrementInteger`
- 337:`IncrementInteger`
- 348:`DecrementInteger`
- 348:`IncrementInteger`
- 356:`UnwrapStrReplace`
- 383:`LogicalNot`
- 385:`Concat`
- 385:`ConcatOperandRemoval`
- 385:`ConcatOperandRemoval`
- 386:`IncrementInteger`
- 386:`ConcatOperandRemoval`
- 416:`DecrementInteger`
- 416:`IncrementInteger`
- 424:`ArrayOneItem`
- 446:`Ternary`
- 466:`ArrayItemRemoval`
- 466:`MethodCallRemoval`
- 482:`ConcatOperandRemoval`
- 483:`ConcatOperandRemoval`
- 518:`DecrementInteger`
- 518:`IncrementInteger`
- 518:`DecrementInteger`
- 518:`IncrementInteger`
- 518:`FalseValue`
- 527:`GreaterThan`
- 527:`LogicalAnd`
- 540:`ArrayItemRemoval`
- 540:`MethodCallRemoval`

### CronService.php (71)

- 41:`DecrementInteger`
- 41:`IncrementInteger`
- 41:`DecrementInteger`
- 41:`IncrementInteger`
- 41:`Multiplication`
- 41:`MethodCallRemoval`
- 42:`DecrementInteger`
- 42:`IncrementInteger`
- 42:`IncrementInteger`
- 42:`DecrementInteger`
- 42:`Multiplication`
- 42:`DecrementInteger`
- 42:`Multiplication`
- 42:`IncrementInteger`
- 42:`MethodCallRemoval`
- 43:`DecrementInteger`
- 43:`IncrementInteger`
- 43:`DecrementInteger`
- 43:`IncrementInteger`
- 43:`Multiplication`
- 43:`DecrementInteger`
- 43:`IncrementInteger`
- 43:`Multiplication`
- 43:`MethodCallRemoval`
- 44:`IncrementInteger`
- 44:`DecrementInteger`
- 44:`DecrementInteger`
- 44:`IncrementInteger`
- 44:`Multiplication`
- 44:`MethodCallRemoval`
- 45:`DecrementInteger`
- 45:`IncrementInteger`
- 45:`DecrementInteger`
- 45:`IncrementInteger`
- 45:`Multiplication`
- 45:`IncrementInteger`
- 45:`DecrementInteger`
- 45:`Multiplication`
- 45:`MethodCallRemoval`
- 46:`DecrementInteger`
- 46:`IncrementInteger`
- 46:`DecrementInteger`
- 46:`IncrementInteger`
- 46:`Multiplication`
- 46:`DecrementInteger`
- 46:`IncrementInteger`
- 46:`Multiplication`
- 46:`MethodCallRemoval`
- 76:`Throw_`
- 80:`Concat`
- 80:`ConcatOperandRemoval`
- 80:`ConcatOperandRemoval`
- 80:`FunctionCallRemoval`
- 90:`LessThanOrEqualTo`
- 90:`LessThanOrEqualToNegotiation`
- 91:`ReturnRemoval`
- 174:`ConcatOperandRemoval`
- 175:`FunctionCallRemoval`
- 183:`ConcatOperandRemoval`
- 184:`FunctionCallRemoval`
- 192:`DecrementInteger`
- 192:`IncrementInteger`
- 192:`DecrementInteger`
- 192:`Multiplication`
- 192:`IncrementInteger`
- 194:`GreaterThan`
- 194:`GreaterThanNegotiation`
- 212:`GreaterThan`
- 212:`GreaterThanNegotiation`
- 230:`GreaterThan`
- 230:`GreaterThanNegotiation`

### ReportQueryRepository.php (54)

- 28:`LogicalAndAllSubExprNegation`
- 28:`LogicalAndSingleSubExprNegation`
- 56:`ReturnRemoval`
- 93:`Coalesce`
- 103:`DecrementInteger`
- 103:`IncrementInteger`
- 104:`DecrementInteger`
- 104:`IncrementInteger`
- 112:`Coalesce`
- 120:`DecrementInteger`
- 120:`IncrementInteger`
- 120:`DecrementInteger`
- 120:`IncrementInteger`
- 129:`DecrementInteger`
- 130:`DecrementInteger`
- 130:`IncrementInteger`
- 130:`DecrementInteger`
- 130:`IncrementInteger`
- 133:`MethodCallRemoval`
- 162:`MethodCallRemoval`
- 181:`LogicalNot`
- 190:`MethodCallRemoval`
- 192:`LogicalNot`
- 192:`LogicalAnd`
- 192:`LogicalAndAllSubExprNegation`
- 192:`LogicalAndNegation`
- 195:`LogicalNot`
- 195:`LogicalAndAllSubExprNegation`
- 202:`MethodCallRemoval`
- 204:`LogicalNot`
- 204:`LogicalAnd`
- 204:`LogicalAnd`
- 204:`LogicalAndAllSubExprNegation`
- 204:`LogicalAndNegation`
- 213:`LogicalNot`
- 249:`Division`
- 249:`RoundingFamily`
- 250:`GreaterThan`
- 250:`GreaterThanNegotiation`
- 250:`GreaterThan`
- 250:`LogicalAndAllSubExprNegation`
- 263:`Coalesce`
- 265:`Coalesce`
- 266:`Coalesce`
- 276:`DecrementInteger`
- 276:`IncrementInteger`
- 277:`Coalesce`
- 278:`Coalesce`
- 279:`Coalesce`
- 281:`DecrementInteger`
- 281:`IncrementInteger`
- 281:`Coalesce`
- 307:`LogicalAnd`
- 319:`LogicalAnd`

### ReportLifecycleRepository.php (52)

- 23:`LogicalAndAllSubExprNegation`
- 23:`LogicalAndSingleSubExprNegation`
- 44:`ArrayItemRemoval`
- 44:`MethodCallRemoval`
- 46:`ArrayItem`
- 76:`ConcatOperandRemoval`
- 76:`Concat`
- 76:`ConcatOperandRemoval`
- 76:`Concat`
- 76:`ConcatOperandRemoval`
- 76:`Concat`
- 76:`ConcatOperandRemoval`
- 76:`Concat`
- 76:`ConcatOperandRemoval`
- 88:`MethodCallRemoval`
- 89:`ReturnRemoval`
- 98:`ConcatOperandRemoval`
- 98:`Concat`
- 98:`ConcatOperandRemoval`
- 98:`Concat`
- 98:`Concat`
- 98:`ConcatOperandRemoval`
- 98:`ConcatOperandRemoval`
- 98:`Concat`
- 98:`ConcatOperandRemoval`
- 129:`MethodCallRemoval`
- 159:`MethodCallRemoval`
- 167:`MethodCallRemoval`
- 191:`MethodCallRemoval`
- 194:`Concat`
- 194:`ConcatOperandRemoval`
- 194:`ConcatOperandRemoval`
- 199:`MethodCallRemoval`
- 226:`MethodCallRemoval`
- 226:`ArrayItemRemoval`
- 229:`Identical`
- 229:`LogicalNot`
- 229:`LogicalAnd`
- 244:`ConcatOperandRemoval`
- 244:`Concat`
- 244:`ConcatOperandRemoval`
- 244:`Concat`
- 244:`ConcatOperandRemoval`
- 261:`MethodCallRemoval`
- 262:`ReturnRemoval`
- 284:`MethodCallRemoval`
- 309:`LogicalAnd`
- 309:`LogicalAndAllSubExprNegation`
- 309:`LogicalAndNegation`
- 309:`LogicalAndSingleSubExprNegation`
- 309:`LogicalAndSingleSubExprNegation`
- 310:`MethodCallRemoval`

### SessionService.php (49)

- 50:`FunctionCallRemoval`
- 68:`LogicalAnd`
- 68:`LogicalAndAllSubExprNegation`
- 68:`LogicalAndNegation`
- 68:`LogicalAndSingleSubExprNegation`
- 68:`LogicalOr`
- 68:`Identical`
- 68:`LogicalOrAllSubExprNegation`
- 68:`LogicalOrNegation`
- 74:`FunctionCallRemoval`
- 80:`LogicalAnd`
- 80:`LogicalAndAllSubExprNegation`
- 80:`LogicalAndNegation`
- 80:`LogicalAndSingleSubExprNegation`
- 80:`Identical`
- 80:`LogicalOr`
- 80:`LogicalOrAllSubExprNegation`
- 80:`LogicalOrNegation`
- 81:`Coalesce`
- 83:`LogicalNot`
- 83:`LogicalAnd`
- 83:`LogicalNot`
- 83:`LogicalAndAllSubExprNegation`
- 83:`LogicalAndNegation`
- 84:`IncrementInteger`
- 84:`DecrementInteger`
- 84:`DecrementInteger`
- 84:`IncrementInteger`
- 84:`UnwrapSubstr`
- 84:`Concat`
- 84:`ConcatOperandRemoval`
- 84:`ConcatOperandRemoval`
- 84:`Concat`
- 84:`ConcatOperandRemoval`
- 84:`Concat`
- 84:`ConcatOperandRemoval`
- 84:`Concat`
- 84:`ConcatOperandRemoval`
- 84:`Ternary`
- 84:`Concat`
- 84:`ConcatOperandRemoval`
- 84:`Concat`
- 84:`ConcatOperandRemoval`
- 84:`Coalesce`
- 84:`Concat`
- 84:`ConcatOperandRemoval`
- 84:`Concat`
- 84:`ConcatOperandRemoval`
- 84:`FunctionCallRemoval`

### CustomFieldsService.php (46)

- 75:`Continue_`
- 122:`Foreach_`
- 124:`LogicalNot`
- 124:`LogicalAnd`
- 124:`LogicalAndAllSubExprNegation`
- 125:`IncrementInteger`
- 125:`Identical`
- 137:`Continue_`
- 144:`ArrayOneItem`
- 202:`Coalesce`
- 204:`IncrementInteger`
- 204:`LogicalOr`
- 204:`ReturnRemoval`
- 216:`Coalesce`
- 216:`Coalesce`
- 218:`DecrementInteger`
- 218:`IncrementInteger`
- 221:`Concat`
- 221:`ConcatOperandRemoval`
- 221:`ConcatOperandRemoval`
- 221:`Concat`
- 226:`LogicalAnd`
- 227:`Concat`
- 227:`ConcatOperandRemoval`
- 227:`ConcatOperandRemoval`
- 227:`Concat`
- 231:`MBString`
- 231:`GreaterThan`
- 232:`Concat`
- 232:`ConcatOperandRemoval`
- 232:`ConcatOperandRemoval`
- 232:`Concat`
- 232:`ConcatOperandRemoval`
- 232:`Concat`
- 232:`Concat`
- 232:`ConcatOperandRemoval`
- 234:`MBString`
- 234:`GreaterThan`
- 235:`Concat`
- 235:`ConcatOperandRemoval`
- 235:`ConcatOperandRemoval`
- 235:`Concat`
- 235:`ConcatOperandRemoval`
- 235:`Concat`
- 235:`Concat`
- 235:`ConcatOperandRemoval`

### ConfigService.php (37)

- 57:`MethodCallRemoval`
- 119:`ArrayItemRemoval`
- 142:`LogicalOrAllSubExprNegation`
- 143:`FalseValue`
- 164:`ReturnRemoval`
- 169:`IfNegation`
- 172:`DecrementInteger`
- 172:`IncrementInteger`
- 172:`Concat`
- 172:`ConcatOperandRemoval`
- 172:`ConcatOperandRemoval`
- 173:`Concat`
- 173:`ConcatOperandRemoval`
- 173:`ConcatOperandRemoval`
- 174:`LogicalNot`
- 177:`UnwrapRtrim`
- 177:`Concat`
- 177:`ConcatOperandRemoval`
- 177:`ConcatOperandRemoval`
- 179:`LogicalNot`
- 182:`DecrementInteger`
- 182:`IncrementInteger`
- 182:`Concat`
- 182:`ConcatOperandRemoval`
- 182:`ConcatOperandRemoval`
- 185:`Foreach_`
- 187:`Ternary`
- 190:`NotIdentical`
- 190:`NotIdentical`
- 190:`LogicalAnd`
- 190:`PregMatchMatches`
- 190:`PregMatchRemoveCaret`
- 190:`PregMatchRemoveFlags`
- 190:`Identical`
- 190:`LogicalAndAllSubExprNegation`
- 190:`LogicalAndNegation`
- 192:`ReturnRemoval`

### FormattingService.php (32)

- 27:`ReturnRemoval`
- 105:`LogicalAnd`
- 114:`MatchArmRemoval`
- 114:`MatchArmRemoval`
- 114:`MatchArmRemoval`
- 149:`NotIdentical`
- 149:`LogicalNot`
- 149:`LogicalAnd`
- 150:`ReturnRemoval`
- 198:`MBString`
- 234:`Assignment`
- 274:`ReturnRemoval`
- 285:`DecrementInteger`
- 285:`IncrementInteger`
- 285:`Coalesce`
- 286:`LessThan`
- 286:`LogicalOr`
- 289:`Multiplication`
- 292:`Division`
- 292:`Plus`
- 293:`OneZeroFloat`
- 303:`Spaceship`
- 303:`FunctionCallRemoval`
- 306:`Ternary`
- 307:`Concat`
- 307:`ConcatOperandRemoval`
- 307:`Concat`
- 307:`ConcatOperandRemoval`
- 313:`Concat`
- 313:`ConcatOperandRemoval`
- 313:`Concat`
- 313:`ConcatOperandRemoval`

### ReportService.php (30)

- 43:`Coalesce`
- 53:`MethodCallRemoval`
- 205:`MethodCallRemoval`
- 218:`NotIdentical`
- 255:`GreaterThan`
- 267:`GreaterThan`
- 285:`MethodCallRemoval`
- 292:`GreaterThan`
- 292:`GreaterThanNegotiation`
- 294:`GreaterThanOrEqualTo`
- 307:`GreaterThan`
- 320:`GreaterThan`
- 329:`Coalesce`
- 330:`Coalesce`
- 336:`IfNegation`
- 337:`UnwrapArrayMerge`
- 337:`UnwrapArrayMerge`
- 339:`Coalesce`
- 340:`Coalesce`
- 360:`ReturnRemoval`
- 367:`UnwrapArrayValues`
- 382:`LogicalOr`
- 383:`ReturnRemoval`
- 402:`ArrayItemRemoval`
- 403:`DecrementInteger`
- 403:`IncrementInteger`
- 403:`Coalesce`
- 406:`Identical`
- 425:`Identical`
- 431:`Identical`

### UserRepository.php (26)

- 26:`LogicalAnd`
- 26:`LogicalAndAllSubExprNegation`
- 26:`LogicalAndNegation`
- 26:`LogicalAndSingleSubExprNegation`
- 26:`LogicalAndSingleSubExprNegation`
- 97:`UnwrapArrayMap`
- 102:`DecrementInteger`
- 132:`IncrementInteger`
- 132:`DecrementInteger`
- 137:`GreaterThan`
- 169:`Throw_`
- 208:`GreaterThan`
- 227:`GreaterThan`
- 271:`DecrementInteger`
- 271:`IncrementInteger`
- 288:`GreaterThan`
- 298:`GreaterThan`
- 303:`ConcatOperandRemoval`
- 307:`ArrayItemRemoval`
- 307:`MethodCallRemoval`
- 308:`GreaterThan`
- 308:`GreaterThanNegotiation`
- 321:`MethodCallRemoval`
- 347:`DecrementInteger`
- 347:`IncrementInteger`
- 393:`ReturnRemoval`

### EmailOutboxRepository.php (24)

- 51:`LogicalAnd`
- 51:`LogicalAndAllSubExprNegation`
- 51:`LogicalAndNegation`
- 51:`LogicalAndSingleSubExprNegation`
- 51:`LogicalAndSingleSubExprNegation`
- 82:`Concat`
- 82:`ConcatOperandRemoval`
- 82:`ConcatOperandRemoval`
- 121:`ReturnRemoval`
- 125:`LogicalNot`
- 145:`MethodCallRemoval`
- 148:`IfNegation`
- 160:`DecrementInteger`
- 160:`IncrementInteger`
- 286:`MethodCallRemoval`
- 289:`ReturnRemoval`
- 329:`LessThan`
- 416:`LessThan`
- 419:`AssignCoalesce`
- 440:`MethodCallRemoval`
- 443:`DecrementInteger`
- 443:`IncrementInteger`
- 444:`DecrementInteger`
- 444:`IncrementInteger`

### ReportWriteRepository.php (22)

- 24:`LogicalAndAllSubExprNegation`
- 24:`LogicalAndSingleSubExprNegation`
- 104:`DecrementInteger`
- 104:`IncrementInteger`
- 106:`DecrementInteger`
- 106:`IncrementInteger`
- 106:`Ternary`
- 142:`MethodCallRemoval`
- 176:`DecrementInteger`
- 176:`IncrementInteger`
- 176:`Ternary`
- 178:`DecrementInteger`
- 178:`IncrementInteger`
- 196:`NotIdentical`
- 196:`LogicalOr`
- 196:`LogicalOrAllSubExprNegation`
- 196:`LogicalOrNegation`
- 208:`ConcatOperandRemoval`
- 231:`IfNegation`
- 232:`MethodCallRemoval`
- 271:`ReturnRemoval`
- 290:`Continue_`

### AuditRepository.php (20)

- 19:`LogicalAnd`
- 19:`LogicalAndAllSubExprNegation`
- 19:`LogicalAndNegation`
- 19:`LogicalAndSingleSubExprNegation`
- 19:`LogicalAndSingleSubExprNegation`
- 46:`DecrementInteger`
- 46:`IncrementInteger`
- 73:`DecrementInteger`
- 73:`DecrementInteger`
- 73:`IncrementInteger`
- 99:`Concat`
- 99:`ConcatOperandRemoval`
- 99:`Concat`
- 99:`ConcatOperandRemoval`
- 155:`PublicVisibility`
- 158:`ArrayItemRemoval`
- 158:`MethodCallRemoval`
- 168:`PublicVisibility`
- 171:`ArrayItemRemoval`
- 171:`MethodCallRemoval`

### AssetService.php (18)

- 18:`Concat`
- 42:`DecrementInteger`
- 42:`Concat`
- 42:`IncrementInteger`
- 42:`ConcatOperandRemoval`
- 42:`ConcatOperandRemoval`
- 42:`Concat`
- 47:`UnwrapStrToLower`
- 48:`ArrayItemRemoval`
- 58:`Coalesce`
- 60:`Identical`
- 65:`Concat`
- 65:`ConcatOperandRemoval`
- 65:`ConcatOperandRemoval`
- 65:`Concat`
- 65:`ConcatOperandRemoval`
- 65:`Concat`
- 65:`ConcatOperandRemoval`

### RegistryRepository.php (17)

- 21:`LogicalAnd`
- 21:`LogicalAndAllSubExprNegation`
- 21:`LogicalAndNegation`
- 21:`LogicalAndSingleSubExprNegation`
- 21:`LogicalAndSingleSubExprNegation`
- 138:`GreaterThan`
- 157:`DecrementInteger`
- 157:`IncrementInteger`
- 163:`DecrementInteger`
- 163:`IncrementInteger`
- 169:`DecrementInteger`
- 169:`IncrementInteger`
- 170:`DecrementInteger`
- 170:`IncrementInteger`
- 187:`DecrementInteger`
- 187:`IncrementInteger`
- 187:`Coalesce`

### RegistryCardService.php (16)

- 27:`ReturnRemoval`
- 43:`DecrementInteger`
- 43:`IncrementInteger`
- 43:`Coalesce`
- 44:`DecrementInteger`
- 44:`Coalesce`
- 57:`Identical`
- 57:`Identical`
- 57:`LogicalOrNegation`
- 57:`LogicalOrAllSubExprNegation`
- 60:`DecrementInteger`
- 60:`IncrementInteger`
- 60:`Ternary`
- 72:`Coalesce`
- 73:`ArrayItemRemoval`
- 74:`ArrayItemRemoval`

### RegistryFieldRepository.php (15)

- 19:`LogicalAnd`
- 19:`LogicalAndAllSubExprNegation`
- 19:`LogicalAndNegation`
- 19:`LogicalAndSingleSubExprNegation`
- 19:`LogicalAndSingleSubExprNegation`
- 56:`LogicalOr`
- 58:`PregMatchRemoveCaret`
- 58:`PregMatchRemoveDollar`
- 60:`Concat`
- 60:`ConcatOperandRemoval`
- 60:`ConcatOperandRemoval`
- 60:`Concat`
- 60:`ConcatOperandRemoval`
- 81:`ArrayItemRemoval`
- 81:`MethodCallRemoval`

### ReportResponseRepository.php (15)

- 25:`DecrementInteger`
- 25:`IncrementInteger`
- 35:`LogicalAnd`
- 35:`LogicalAndAllSubExprNegation`
- 35:`LogicalAndNegation`
- 35:`LogicalAndSingleSubExprNegation`
- 35:`LogicalAndSingleSubExprNegation`
- 54:`ArrayItemRemoval`
- 54:`MethodCallRemoval`
- 57:`ArrayOneItem`
- 83:`ReturnRemoval`
- 85:`UnwrapArrayValues`
- 88:`DecrementInteger`
- 88:`IncrementInteger`
- 102:`LogicalAnd`

### ExportService.php (14)

- 135:`ReturnRemoval`
- 161:`LogicalOr`
- 162:`Continue_`
- 170:`Continue_`
- 272:`Concat`
- 272:`ConcatOperandRemoval`
- 272:`ConcatOperandRemoval`
- 377:`PublicVisibility`
- 387:`ArrayItemRemoval`
- 387:`ArrayItemRemoval`
- 387:`UnwrapStrReplace`
- 399:`PublicVisibility`
- 403:`Foreach_`
- 435:`ReturnRemoval`

### StatsRepository.php (13)

- 28:`LogicalAnd`
- 28:`LogicalAndAllSubExprNegation`
- 28:`LogicalAndNegation`
- 28:`LogicalAndSingleSubExprNegation`
- 28:`LogicalAndSingleSubExprNegation`
- 38:`DecrementInteger`
- 53:`DecrementInteger`
- 59:`DecrementInteger`
- 76:`DecrementInteger`
- 76:`IncrementInteger`
- 76:`DecrementInteger`
- 76:`IncrementInteger`
- 76:`FalseValue`

### ReportAgentRepository.php (12)

- 20:`LogicalAndAllSubExprNegation`
- 20:`LogicalAndSingleSubExprNegation`
- 33:`DecrementInteger`
- 33:`IncrementInteger`
- 43:`Identical`
- 44:`GreaterThan`
- 44:`GreaterThanNegotiation`
- 76:`ArrayOneItem`
- 87:`ArrayItemRemoval`
- 87:`MethodCallRemoval`
- 90:`ArrayOneItem`
- 111:`CastBool`

### ReportRepository.php (12)

- 24:`LogicalAndAllSubExprNegation`
- 24:`LogicalAnd`
- 24:`LogicalAndNegation`
- 24:`LogicalAndSingleSubExprNegation`
- 24:`LogicalAndSingleSubExprNegation`
- 42:`DecrementInteger`
- 42:`IncrementInteger`
- 42:`DecrementInteger`
- 42:`IncrementInteger`
- 95:`ArrayItemRemoval`
- 95:`MethodCallRemoval`
- 97:`Ternary`

### CookieService.php (12)

- 23:`PublicVisibility`
- 25:`LogicalAndNegation`
- 36:`FunctionCallRemoval`
- 37:`IfNegation`
- 50:`ReturnRemoval`
- 53:`ReturnRemoval`
- 58:`IfNegation`
- 63:`FunctionCallRemoval`
- 66:`ArrayItemRemoval`
- 67:`DecrementInteger`
- 67:`IncrementInteger`
- 67:`Minus`

### ConfigRepository.php (10)

- 18:`LogicalAnd`
- 18:`LogicalAndAllSubExprNegation`
- 18:`LogicalAndNegation`
- 18:`LogicalAndSingleSubExprNegation`
- 18:`LogicalAndSingleSubExprNegation`
- 56:`ReturnRemoval`
- 92:`Minus`
- 107:`LogicalOr`
- 112:`GreaterThanOrEqualTo`
- 112:`LogicalOr`

### CryptoService.php (9)

- 31:`IncrementInteger`
- 31:`UnwrapSubstr`
- 46:`LogicalOr`
- 47:`ReturnRemoval`
- 50:`LogicalOr`
- 51:`ReturnRemoval`
- 53:`IncrementInteger`
- 53:`UnwrapSubstr`
- 55:`LessThan`

### HttpService.php (8)

- 24:`LogicalAnd`
- 69:`FunctionCallRemoval`
- 70:`FunctionCallRemoval`
- 71:`FunctionCallRemoval`
- 72:`FunctionCallRemoval`
- 75:`FunctionCallRemoval`
- 76:`FunctionCallRemoval`
- 77:`FunctionCallRemoval`

### RegistryPolicy.php (8)

- 76:`NotIdentical`
- 76:`LogicalAnd`
- 77:`IncrementInteger`
- 77:`ReturnRemoval`
- 92:`NotIdentical`
- 92:`LogicalAnd`
- 94:`Ternary`
- 94:`ReturnRemoval`

### SessionTokenService.php (8)

- 41:`LessThan`
- 51:`DecrementInteger`
- 51:`IncrementInteger`
- 52:`GreaterThan`
- 71:`MethodCallRemoval`
- 87:`Identical`
- 88:`LogicalNot`
- 88:`FunctionCallRemoval`

### NotificationRepository.php (7)

- 18:`LogicalAnd`
- 18:`LogicalAndAllSubExprNegation`
- 18:`LogicalAndNegation`
- 18:`LogicalAndSingleSubExprNegation`
- 18:`LogicalAndSingleSubExprNegation`
- 37:`Ternary`
- 38:`ArrayOneItem`

### SQLiteSessionHandler.php (7)

- 27:`TrueValue`
- 33:`ArrayItemRemoval`
- 33:`MethodCallRemoval`
- 35:`Identical`
- 36:`ReturnRemoval`
- 38:`Coalesce`
- 48:`ArrayItemRemoval`

### SessionRepository.php (6)

- 34:`DecrementInteger`
- 34:`IncrementInteger`
- 34:`PublicVisibility`
- 36:`Minus`
- 38:`ArrayItemRemoval`
- 38:`MethodCallRemoval`

### SiteRepository.php (6)

- 18:`LogicalAnd`
- 18:`LogicalAndAllSubExprNegation`
- 18:`LogicalAndNegation`
- 18:`LogicalAndSingleSubExprNegation`
- 18:`LogicalAndSingleSubExprNegation`
- 42:`ArrayOneItem`

### RegistryFieldValueRepository.php (5)

- 17:`LogicalAnd`
- 17:`LogicalAndAllSubExprNegation`
- 17:`LogicalAndNegation`
- 17:`LogicalAndSingleSubExprNegation`
- 17:`LogicalAndSingleSubExprNegation`

### AccessService.php (5)

- 111:`FalseValue`
- 111:`ReturnRemoval`
- 153:`LogicalAnd`
- 168:`ReturnRemoval`
- 184:`AssignCoalesce`

### UserService.php (5)

- 57:`Identical`
- 58:`FunctionCallRemoval`
- 169:`DecrementInteger`
- 169:`IncrementInteger`
- 240:`ArrayOneItem`

### AuthService.php (4)

- 236:`UnwrapStrToLower`
- 238:`MethodCallRemoval`
- 240:`FunctionCallRemoval`
- 265:`ReturnRemoval`

### OutboxHealthService.php (4)

- 40:`PublicVisibility`
- 44:`ArrayItemRemoval`
- 61:`LogicalAndAllSubExprNegation`
- 61:`LogicalAndSingleSubExprNegation`

### TransactionManager.php (3)

- 46:`NotIdentical`
- 47:`FunctionCallRemoval`
- 65:`LogicalAnd`

### ReopenReportCommand.php (2)

- 18:`MBString`
- 18:`LessThan`

### ReportAuditRepository.php (2)

- 17:`LogicalAndAllSubExprNegation`
- 17:`LogicalAndSingleSubExprNegation`

### Router.php (2)

- 126:`UnwrapArrayMerge`
- 126:`UnwrapArrayValues`

### SessionManager.php (2)

- 73:`FunctionCall`
- 83:`FunctionCall`

### CreateReportCommand.php (1)

- 95:`DecrementInteger`

### UpdateReportCommand.php (1)

- 130:`ArrayItem`

### OutboxEvent.php (1)

- 35:`UnwrapStrToLower`

### SessionDataService.php (1)

- 90:`MethodCallRemoval`

### StatisticsService.php (1)

- 37:`ArrayOneItem`

