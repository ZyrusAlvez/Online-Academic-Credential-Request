# Chapter II Sources: Review of Related Literature and Studies

Thesis: "Online Academic Credential Request, Tracking, Monitoring, and Performance Analysis System for the Office of the Registrar, University of Perpetual Help System Laguna (UPHSL)"
Researchers: Zyrus R. Alvez and Lucky Angel S. Guevarra. Adviser: Michael M. Orozco, DIT.

Compiled: October 2026.

## How these sources were found and verified

- **Google Scholar access.** Google Scholar result pages could be fetched directly at first. About 10 automated queries went through, then Scholar started showing a "please show you're not a robot" (CAPTCHA) page and blocked further automated searches. Every source below still has the Scholar query a student can type to reproduce the search. Queries marked "(run)" were actually run through Scholar during this research and returned the source on the first page. The others were not run because of the block. Their metadata was checked through other channels instead.
- **Verification channels.** Crossref API (`https://api.crossref.org/works/<DOI>`), DOI resolution (`https://doi.org/...`), DataCite API (for ResearchGate DOIs), Semantic Scholar and OpenAlex (for abstracts), arXiv full texts (for benchmark numbers), the authors' own preprint PDFs, journal article pages, and official government pages (lawphil.net for the full text of Republic Acts, the CHED site for the CHED Memorandum Order, sdgs.un.org and desapublications.un.org for UN documents). The Official Gazette and ARTA sites returned HTTP 403 to automated access, so the full text of RA 11032 was read on lawphil.net instead.
- **Rule used.** A source is listed here only if its title, authors, year and venue were confirmed through at least one of these channels. Summaries are paraphrased from the abstract or full text that was actually read. If something could not be confirmed, it is flagged in the source entry and again in the final double-check list.
- **APA notes.** References follow APA 7th edition. Philippine laws follow the usual APA legal format (the Act's name, number, year and a URL). Students should still check capitalisation and italics against their department's APA template.

Legend: **RL** = Related Literature, **RS** = Related Study. **F** = Foreign, **L** = Local (Philippine).

---

## A. Foundations: data mining, KDD, CRISP-DM, process mining, BPM (Sections 2.1 and 2.2)

### 1. Fayyad, Piatetsky-Shapiro and Smyth (1996), KDD

- **APA:** Fayyad, U., Piatetsky-Shapiro, G., & Smyth, P. (1996). From data mining to knowledge discovery in databases. *AI Magazine, 17*(3), 37-54. https://doi.org/10.1609/aimag.v17i3.1230
- **Type:** RL, F. Peer-reviewed magazine article (AAAI).
- **Verified:** The DOI resolves (HTTP 302) to the AAAI AI Magazine article page (aaai.org/ojs/index.php/aimagazine/article/view/1230). Crossref returned 404 for this DOI because AI Magazine DOIs are now handled by a different registration path, but the DOI itself resolves correctly. The page range 37-54 is the commonly cited range and was not re-checked on the publisher PDF (see double-check list).
- **Scholar query:** `From data mining to knowledge discovery in databases Fayyad`
- **Summary:** The article draws a line between KDD, which is the whole process of finding useful knowledge in data, and data mining, which is only one step inside it (applying algorithms to pull patterns out of data). The KDD process it describes runs through selection, preprocessing, transformation, data mining, and interpretation/evaluation, and the authors stress that the process is iterative and interactive. It also sorts data mining methods into families (classification, regression, clustering, summarisation, dependency modelling, change and deviation detection) and warns that mining without the surrounding steps can produce meaningless patterns.
- **Relevance:** This is the thesis's main analytics framework. The system's pipeline from event log to cleaned features, regression model, and dashboard interpretation maps onto the KDD steps.
- **Section:** 2.1, 2.2

### 2. van der Aalst (2016), Process Mining: Data Science in Action

- **APA:** van der Aalst, W. (2016). *Process mining: Data science in action* (2nd ed.). Springer. https://doi.org/10.1007/978-3-662-49851-4
- **Type:** RL, F. Book (scholarly, Springer).
- **Verified:** Crossref record for the DOI: title "Process Mining", author van der Aalst, Wil, Springer Berlin Heidelberg, 2016, type book.
- **Scholar query:** `Process mining: data science in action van der Aalst`
- **Summary:** This is the standard textbook on process mining. It defines an event log as a set of cases, each made up of events that carry at least a case ID, an activity name and a timestamp, often with a resource as well. It covers the three main types of process mining: discovery (building a process model from the log), conformance checking (comparing the log with a model), and enhancement (adding performance information such as waiting times and bottlenecks to a model). It also covers operational support, meaning using event data to predict and recommend while cases are still running.
- **Relevance:** This supports the design of the append-only event log (case ID, activity, timestamp, resource/department), the process discovery, and the bottleneck analysis features. It is the second half of the thesis framework.
- **Section:** 2.2

### 3. van der Aalst et al. (2012), Process Mining Manifesto

- **APA:** van der Aalst, W., Adriansyah, A., de Medeiros, A. K. A., Arcieri, F., Baier, T., Blickle, T., Bose, J. C., van den Brand, P., Brandtjen, R., Buijs, J., Burattin, A., Carmona, J., Castellanos, M., Claes, J., Cook, J., Costantini, N., Curbera, F., Damiani, E., de Leoni, M., ... Wynn, M. (2012). Process mining manifesto. In F. Daniel, K. Barkaoui, & S. Dustdar (Eds.), *Business Process Management Workshops* (Lecture Notes in Business Information Processing, pp. 169-194). Springer. https://doi.org/10.1007/978-3-642-28108-2_19
- **Type:** RL, F. Peer-reviewed workshop proceedings chapter (IEEE Task Force on Process Mining).
- **Verified:** Crossref: title "Process Mining Manifesto", LNBIP, Springer, 2012, pp. 169-194, more than 70 authors listed. The editor names and the LNBIP volume number (99) are from general knowledge and were not in the Crossref output (see double-check list).
- **Scholar query:** `process mining manifesto`
- **Summary:** This position paper was written by the IEEE Task Force on Process Mining with authors from many universities and companies. It sets out guiding principles for process mining, such as treating event data as first-class citizens and making sure logs have good quality and clear meaning. It also lists open challenges, including dealing with noisy or incomplete logs, concept drift, and offering operational support. One of its key messages is that the quality of the event log determines the quality of the analysis.
- **Relevance:** This justifies building the system so that every hand-off is recorded at the source as a complete, timestamped, append-only event. That makes the log "process-mining ready" instead of having to be rebuilt from scattered records later.
- **Section:** 2.2

### 4. Dumas, La Rosa, Mendling and Reijers (2018), Fundamentals of BPM

- **APA:** Dumas, M., La Rosa, M., Mendling, J., & Reijers, H. A. (2018). *Fundamentals of business process management* (2nd ed.). Springer. https://doi.org/10.1007/978-3-662-56509-4
- **Type:** RL, F. Book (scholarly textbook).
- **Verified:** Crossref: title "Fundamentals of Business Process Management", authors Dumas, La Rosa, Mendling, Reijers, Springer Berlin Heidelberg, 2018, type book. The "2nd ed." label is based on the 2018 Springer edition (the first edition was 2013).
- **Scholar query:** `fundamentals of business process management Dumas La Rosa Mendling Reijers`
- **Summary:** The book teaches the BPM lifecycle: identifying processes, discovering them (modelling the current process), analysing them, redesigning them, implementing them, and monitoring and controlling them. It explains process performance measures (time, cost, quality and flexibility), with cycle time, waiting time and processing time as the main time measures. It covers quantitative and qualitative process analysis, including finding waste and bottlenecks, and it treats process mining as the main data-driven tool for the monitoring phase.
- **Relevance:** This frames multi-office credential routing as a business process. It gives the vocabulary for the system's performance measures (turnaround/cycle time, per-office waiting time) and for the monitor-and-improve cycle.
- **Section:** 2.1, 2.2

### 5. Schröer, Kruse and Gómez (2021), CRISP-DM literature review

- **APA:** Schröer, C., Kruse, F., & Gómez, J. M. (2021). A systematic literature review on applying CRISP-DM process model. *Procedia Computer Science, 181*, 526-534. https://doi.org/10.1016/j.procs.2021.01.199
- **Type:** RS, F. Peer-reviewed (conference proceedings published as a journal volume, Elsevier).
- **Verified:** Crossref: title, authors (Schröer, Christoph; Kruse, Felix; Gómez, Jorge Marx), Procedia Computer Science vol. 181, pp. 526-534, 2021. Abstract read on OpenAlex.
- **Scholar query:** `A systematic literature review on applying CRISP-DM process model`
- **Summary:** About twenty years after CRISP-DM was released in 2000, the authors reviewed recent data mining case studies from IEEE, ScienceDirect and ACM that used it. They describe how each of the six CRISP-DM phases (business understanding, data understanding, data preparation, modelling, evaluation, deployment) was carried out in practice and list best practices for each phase. Their main finding is that CRISP-DM is still the de facto standard, but most studies do not plan for or report a deployment phase. They also offer a template for structuring CRISP-DM studies.
- **Relevance:** This lets Section 2.2 compare CRISP-DM with KDD and explain why the thesis uses KDD while borrowing CRISP-DM's emphasis on deployment. Here the model is deployed inside the live routing workflow, which is the gap this review points out.
- **Section:** 2.2

### 6. Hoerl and Kennard (1970), Ridge regression

- **APA:** Hoerl, A. E., & Kennard, R. W. (1970). Ridge regression: Biased estimation for nonorthogonal problems. *Technometrics, 12*(1), 55-67. https://doi.org/10.1080/00401706.1970.10488634
- **Type:** RL, F. Peer-reviewed journal article.
- **Verified:** Crossref: title, authors, Technometrics vol. 12 no. 1, pp. 55-67, February 1970.
- **Scholar query:** `ridge regression biased estimation for nonorthogonal problems` (this query hit the CAPTCHA block, so the record was checked on Crossref instead)
- **Summary:** The paper deals with ordinary least squares regression when the predictors are strongly correlated with each other (multicollinearity). In that case the least squares coefficients become unstable and can have very large variance. The authors propose adding a small positive constant to the diagonal of the X'X matrix before solving. This accepts a little bias in return for a large drop in variance, and it often gives a lower overall mean squared error. They also introduce the "ridge trace", a plot of the coefficients against the constant, to help choose its value.
- **Relevance:** This is the original source for the Ridge regularisation used in the turnaround-time regression model. It explains why Ridge suits correlated predictors such as credential type, office queue lengths and calendar features.
- **Section:** 2.2 (ML frameworks), 2.5

### 6a. Wirth and Hipp (2000), CRISP-DM (partly verified)

- **APA:** Wirth, R., & Hipp, J. (2000). CRISP-DM: Towards a standard process model for data mining. In *Proceedings of the 4th International Conference on the Practical Applications of Knowledge Discovery and Data Mining* (pp. 29-39).
- **Type:** RL, F. Conference paper (no DOI).
- **Verified (partly):** This paper has no DOI and no Crossref record. Web searches confirmed the title, both authors (Rüdiger Wirth, DaimlerChrysler Research and Technology; Jochen Hipp, University of Tübingen) and the venue (4th International Conference on the Practical Applications of Knowledge Discovery and Data Mining, 2000). The page range 29-39 is the commonly cited range and **was not confirmed** from a primary copy. The arXiv paper "The 4+1 Model of Data Science" (arXiv:2311.07631) cites a Wirth and Hipp CRISP-DM essay under the year 1999, so check the year and pages on a copy of the paper before citing.
- **Scholar query:** `CRISP-DM towards a standard process model for data mining Wirth Hipp` (blocked by the CAPTCHA during this research)
- **Summary:** The paper presents CRISP-DM (CRoss-Industry Standard Process for Data Mining), a process model for data mining projects that does not depend on any industry or tool. It was developed by a consortium of DaimlerChrysler, SPSS, NCR and OHRA with partial funding from the European Commission. The model has six phases (business understanding, data understanding, data preparation, modelling, evaluation, deployment) arranged in an iterative cycle. The paper also argues for a standard process model and reports early experience using CRISP-DM in practice.
- **Relevance:** This is the primary source for CRISP-DM, needed when Section 2.2 compares KDD and CRISP-DM. The thesis can explain that it follows KDD but borrows CRISP-DM's business understanding and deployment phases.
- **Section:** 2.2

---

## B. Predictive process monitoring and remaining-time prediction (Sections 2.2, 2.3, 2.5)

### 7. Verenich, Dumas, La Rosa, Maggi and Teinemaa (2019), remaining-time benchmark

- **APA:** Verenich, I., Dumas, M., La Rosa, M., Maggi, F. M., & Teinemaa, I. (2019). Survey and cross-benchmark comparison of remaining time prediction methods in business process monitoring. *ACM Transactions on Intelligent Systems and Technology, 10*(4), 1-34. https://doi.org/10.1145/3331449
- **Type:** RS, F. Peer-reviewed journal article.
- **Verified:** Crossref: title, all five authors, ACM TIST vol. 10 no. 4, pp. 1-34, July 2019. Scholar query (run) returned it as the first result. Full text read from arXiv:1805.02896v2 (May 2018).
- **Scholar query (run):** `survey and cross-benchmark comparison of remaining time prediction methods`
- **Summary:** The authors did a systematic literature review of remaining cycle-time prediction methods for business processes. They found 24 relevant studies, 10 of which made a distinct contribution, and grouped them by input data, process awareness and algorithm family. They then re-ran 16 method variants under one shared setup on 16 public real-life event logs from finance, healthcare, public administration and other domains. Cases were split by time (the earliest 80% for training, the latest 20% for testing), and accuracy was measured as mean absolute error (MAE) in days. LSTM neural networks were the most accurate on 13 of the 16 logs. Different combinations of bucketing and encoding for classical machine learning were not significantly different on average, so the authors recommend the simpler method when accuracy is similar. Note: the published ACM abstract says 17 datasets while the arXiv v2 text says 16, so the numbers quoted in the benchmark table below come from the arXiv preprint and should be checked against the ACM version.
- **Relevance:** This is the main ML benchmark for turnaround prediction. It sets the evaluation design the thesis can copy (temporal split, MAE in days, comparison with simple baselines). It also supports choosing a simple, interpretable regression model, because the simpler methods often perform on par.
- **Section:** 2.3, 2.5 (ML benchmarks)

### 8. Teinemaa, Dumas, La Rosa and Maggi (2019), outcome-oriented benchmark

- **APA:** Teinemaa, I., Dumas, M., La Rosa, M., & Maggi, F. M. (2019). Outcome-oriented predictive process monitoring: Review and benchmark. *ACM Transactions on Knowledge Discovery from Data, 13*(2), 1-57. https://doi.org/10.1145/3301300
- **Type:** RS, F. Peer-reviewed journal article.
- **Verified:** Crossref: title (Crossref lists only the main title, without the subtitle), authors, ACM TKDD vol. 13 no. 2, pp. 1-57, April 2019. Scholar query (run) returned it first. Full text read from arXiv:1707.06766v4.
- **Scholar query (run):** `outcome-oriented predictive process monitoring review and benchmark`
- **Summary:** This study reviews methods that classify a running case into an outcome category, for example whether a deadline will be met or a case will be rejected, using only the events seen so far. It compares 11 representative methods on 24 prediction tasks built from 9 real-life event logs, using AUC as the main metric because it is not affected by class imbalance. XGBoost gave the highest AUC in 15 of the 24 tasks and random forest in 11. Logistic regression was best in 7 tasks and was not significantly worse than XGBoost or random forest in the Nemenyi test. Simple aggregation encodings ("single agg" and "prefix agg") were the best overall choices.
- **Relevance:** This supports the late/on-time (SLA breach) flag as a binary outcome prediction. It shows that a simple, interpretable linear model (logistic regression) can be statistically competitive, which backs the thesis's preference for interpretable regression.
- **Section:** 2.3, 2.5

### 9. Tax, Verenich, La Rosa and Dumas (2017), LSTM predictive monitoring

- **APA:** Tax, N., Verenich, I., La Rosa, M., & Dumas, M. (2017). Predictive business process monitoring with LSTM neural networks. In E. Dubois & K. Pohl (Eds.), *Advanced Information Systems Engineering: CAiSE 2017* (Lecture Notes in Computer Science, Vol. 10253, pp. 477-492). Springer. https://doi.org/10.1007/978-3-319-59536-8_30
- **Type:** RS, F. Peer-reviewed conference paper (CAiSE).
- **Verified:** Crossref: title, four authors, LNCS, Springer, 2017, pp. 477-492. Scholar query (run) returned it first. Full text read from arXiv:1612.02130v2. The editor names and volume number are from general knowledge (see double-check list).
- **Scholar query (run):** `predictive business process monitoring with LSTM neural networks`
- **Summary:** The paper tests long short-term memory (LSTM) recurrent networks as one general model for several prediction tasks: the next activity, the time until the next event, the full remaining sequence of events, and the remaining cycle time. On the Helpdesk and BPI Challenge 2012 (W subprocess) logs, the LSTM models beat the transition-system baseline of van der Aalst et al. (2011) at predicting the time to the next event. The best "all prefixes" MAE was about 3.75 days against 5.67 to 5.83 days for the baseline on Helpdesk, and about 1.56 days against 1.91 to 1.97 days on BPI'12 W. For remaining cycle time, LSTM consistently won on Helpdesk but did worse than the baselines on short prefixes of BPI'12 W, because that log has many repeated events.
- **Relevance:** This gives a deep-learning reference point in the comparison of approaches. It also shows that complex models need large logs and can fail on repetitive processes, which supports starting with an interpretable regression baseline for a new registrar log.
- **Section:** 2.3, 2.5

### 10. van der Aalst, Schonenberg and Song (2011), time prediction using process mining

- **APA:** van der Aalst, W. M. P., Schonenberg, M. H., & Song, M. (2011). Time prediction based on process mining. *Information Systems, 36*(2), 450-475. https://doi.org/10.1016/j.is.2010.09.001
- **Type:** RS, F. Peer-reviewed journal article.
- **Verified:** Crossref: title, authors, Information Systems vol. 36 no. 2, pp. 450-475, April 2011. Scholar query (run) returned it first. Full text read from the author preprint at vdaalst.com/publications/p609.pdf.
- **Scholar query (run):** `time prediction based on process mining`
- **Summary:** The authors build an "annotated transition system" from an event log. Each state is an abstraction of a case's history (for example the set or sequence of activities done so far), and it is annotated with the elapsed, sojourn and remaining times seen in past cases. Predictions for running cases are read from these annotations, and the level of abstraction can be tuned to avoid overfitting. The method was implemented in ProM and evaluated on a simulation log and on real logs from two Dutch municipalities: a WMO social-support request process (796 cases, average flow time 117 days) and a property-tax objection (WOZ) process. On the WMO log, set-based abstractions reached an MAE of about 64 to 67 days, while a simple heuristic (half of the average total flow time) had an MAE of about 81 days. The authors conclude that even simple process-aware abstractions beat average-based heuristics.
- **Relevance:** This is the closest classic precedent for the thesis: predicting completion time of citizen/service requests in a public office from an event log, and showing that this beats average-based estimates. That is exactly the comparison the thesis makes against average-based reporting.
- **Section:** 2.3, 2.5

### 11. Márquez-Chamorro, Resinas and Ruiz-Cortés (2018), survey of predictive monitoring

- **APA:** Márquez-Chamorro, A. E., Resinas, M., & Ruiz-Cortés, A. (2018). Predictive monitoring of business processes: A survey. *IEEE Transactions on Services Computing, 11*(6), 962-977. https://doi.org/10.1109/TSC.2017.2772256
- **Type:** RS, F. Peer-reviewed journal article (survey).
- **Verified:** Crossref: title, authors, IEEE TSC vol. 11 no. 6, pp. 962-977, November/December 2018 issue. Scholar query (run) returned it first (Scholar shows 2017 because of early access). Abstract read on Semantic Scholar.
- **Scholar query (run):** `Marquez-Chamorro predictive monitoring of business processes a survey`
- **Summary:** This survey summarises the main approaches to predicting measurable indicators of running process instances, such as time, outcome or cost, from event logs. It classifies them by computational method (statistical techniques or machine learning), by the type of value predicted, and by the quality metrics used to evaluate them. It also explains the basic concepts and gives an overview of the field for researchers and practitioners.
- **Relevance:** This is the taxonomy the thesis can use in Section 2.2 to place its method: a statistical/ML regression that predicts a numeric time value, evaluated with error metrics.
- **Section:** 2.2, 2.3

### 12. Metzger et al. (2015), comparing and combining predictive monitoring techniques

- **APA:** Metzger, A., Leitner, P., Ivanović, D., Schmieders, E., Franklin, R., Carro, M., Dustdar, S., & Pohl, K. (2015). Comparing and combining predictive business process monitoring techniques. *IEEE Transactions on Systems, Man, and Cybernetics: Systems, 45*(2), 276-290. https://doi.org/10.1109/TSMC.2014.2347265
- **Type:** RS, F. Peer-reviewed journal article.
- **Verified:** Crossref: title, eight authors, IEEE TSMC: Systems vol. 45 no. 2, pp. 276-290, February 2015. Abstract read on OpenAlex.
- **Scholar query:** `comparing and combining predictive business process monitoring techniques`
- **Summary:** The authors compared three kinds of techniques for predicting whether a running process will violate its targets: machine learning, constraint satisfaction, and Quality-of-Service aggregation. They used data from an industrial transport and logistics case and judged the techniques on five accuracy indicators and on how early a prediction is made. When predictions were made halfway through the process, every technique was at least 70% accurate, but they differed in how well they caught violations versus non-violations. Combining techniques helped: constraint satisfaction plus QoS aggregation raised precision by 14%, and machine learning plus constraint satisfaction raised recall by 23%.
- **Relevance:** This supports the SLA-breach flag and shows why violation prediction should be judged with precision and recall, not accuracy alone. It also shows the trade-off between how early a prediction is made and how accurate it is, which applies to predicting at routing time.
- **Section:** 2.3, 2.5

---

## C. Process mining in education, administration and public services (Sections 2.3, 2.5)

### 13. Bogarín, Cerezo and Romero (2018), educational process mining survey

- **APA:** Bogarín, A., Cerezo, R., & Romero, C. (2018). A survey on educational process mining. *WIREs Data Mining and Knowledge Discovery, 8*(1), Article e1230. https://doi.org/10.1002/widm.1230
- **Type:** RS, F. Peer-reviewed journal article (survey).
- **Verified:** Crossref: title, authors, WIREs DMKD vol. 8 no. 1, article e1230, January 2018. Abstract read on Semantic Scholar.
- **Scholar query:** `a survey on educational process mining Bogarin Cerezo Romero`
- **Summary:** The survey presents educational process mining (EPM) as a branch of educational data mining. EPM uses event logs from educational settings to discover, analyse and visualise whole educational processes. It describes the parts of an EPM framework, the problems of working with educational event logs, and the data, tools, techniques and models used. It then groups the main published work by application area.
- **Relevance:** This places the thesis inside educational process mining. It also shows that most EPM work looks at learning processes rather than administrative ones such as credential requests, which is part of the research gap.
- **Section:** 2.3, 2.6

### 14. Caballero-Hernández et al. (2018), bottlenecks in a computer science degree

- **APA:** Caballero-Hernández, J. A., Dodero, J. M., Ruiz-Rube, I., Palomo-Duarte, M., Argudo, J. F., & Domínguez-Jiménez, J. J. (2018). Discovering bottlenecks in a computer science degree through process mining techniques. In *2018 International Symposium on Computers in Education (SIIE)* (pp. 1-6). IEEE. https://doi.org/10.1109/SIIE.2018.8586680
- **Type:** RS, F. Peer-reviewed conference paper.
- **Verified:** Crossref: title, **six** authors, SIIE 2018, IEEE, pp. 1-6, September 2018. Abstract read on Semantic Scholar. **Important:** Chapter 1 lists only four authors. Crossref also lists Palomo-Duarte, M. and Argudo, J. F., so the Chapter 1 reference must be corrected.
- **Scholar query:** `discovering bottlenecks in a computer science degree through process mining`
- **Summary:** The authors wanted to know why students take longer to finish a degree, and they suspected that some compulsory courses act as bottlenecks. They applied process mining tools to performance records of 612 students, compared how often students needed different amounts of time to pass each course, and used this to spot likely bottleneck courses. They end by proposing requirements for deciding that a course is a bottleneck.
- **Relevance:** This is a direct precedent for using process mining to detect bottlenecks in a university process. The thesis moves the same idea from courses to the offices in the credential-request route.
- **Section:** 2.3

### 15. Atastina and Kurniati (2014), student registration process mining

- **APA:** Atastina, I., & Kurniati, A. P. (2014). Student registration process evaluation using process mining case study: IT Telkom. In *Ninth International Conference on Digital Information Management (ICDIM 2014)* (pp. 189-193). IEEE. https://doi.org/10.1109/ICDIM.2014.6991417
- **Type:** RS, F (Indonesia). Peer-reviewed conference paper.
- **Verified:** Crossref: title, two authors, ICDIM 2014, IEEE, pp. 189-193, September 2014. No abstract was available on Semantic Scholar or OpenAlex, so the summary below is based on the title and metadata only.
- **Scholar query:** `student registration process evaluation using process mining IT Telkom`
- **Summary (limited, abstract not read):** As the title says, the paper applies process mining to evaluate the student registration process at IT Telkom (Indonesia). Students should read the paper before citing any specific findings.
- **Relevance:** This is an example of process mining applied to a university administrative (registrar-type) process rather than a learning process.
- **Section:** 2.3

### 16. Thiede, Fuerstenau and Bezerra Barquet (2018), process mining in organisations

- **APA:** Thiede, M., Fuerstenau, D., & Bezerra Barquet, A. P. (2018). How is process mining technology used by organizations? A systematic literature review of empirical studies. *Business Process Management Journal, 24*(4), 900-922. https://doi.org/10.1108/BPMJ-06-2017-0148
- **Type:** RS, F. Peer-reviewed journal article.
- **Verified:** Crossref: title, authors, BPMJ vol. 24 no. 4, pp. 900-922, 2018. Abstract read on OpenAlex.
- **Scholar query:** `how is process mining technology used by organizations systematic literature review`
- **Summary:** The authors built a classification model combining process mining concepts with technology-use frameworks from the ERP and business intelligence fields, and applied it to 144 empirical research papers. They found that most studies look at a single system in a single organisation. Process mining across several systems or organisations, and process mining of mixed physical and digital services, were underrepresented. They call for more complex, service-oriented use cases.
- **Relevance:** Credential processing crosses several offices (Library, Cashier, Dean, Registrar) and mixes digital tracking with physical document release. That is exactly the cross-unit, physical-digital service setting this review says is understudied.
- **Section:** 2.3, 2.6

### 17. dos Santos Garcia et al. (2019), process mining mapping study

- **APA:** dos Santos Garcia, C., Meincheim, A., Faria Junior, E. R., Dallagassa, M. R., Sato, D. M. V., Carvalho, D. R., Santos, E. A. P., & Scalabrin, E. E. (2019). Process mining techniques and applications: A systematic mapping study. *Expert Systems with Applications, 133*, 260-295. https://doi.org/10.1016/j.eswa.2019.05.003
- **Type:** RS, F. Peer-reviewed journal article.
- **Verified:** Crossref: title, eight authors, ESWA vol. 133, pp. 260-295, November 2019. Abstract read on Semantic Scholar. (The original title uses a dash: "Process mining techniques and applications - A systematic mapping study". Crossref lists the first author's family name as "Garcia", given name "Cleiton dos Santos", so some citation styles show "Garcia, C. d. S.". Check this against the department's style.)
- **Scholar query:** `process mining techniques and applications a systematic mapping study`
- **Summary:** This mapping study screened 3,713 articles and kept 1,278 to map process mining research topics, the most used algorithms, and application domains. Process discovery was the most active topic, followed by conformance checking and work on architecture and tools. Healthcare had the most case studies, followed by ICT, manufacturing, education, finance and logistics.
- **Relevance:** This gives evidence that education is an established application domain for process mining, and it shows that discovery and bottleneck analysis are the core techniques the thesis uses.
- **Section:** 2.2, 2.3

---

## D. Local (Philippine) credential request, registrar and document tracking studies (Sections 2.4, 2.5)

### 18. Velasco et al. (2024), online academic document request and tracking

- **APA:** Velasco, M. N., Cabrera, R. Z., Palma, J. B. M., Lalap, J. P., Del Rosario, A. J. C., Panganiban, J. C. D., & Villanueva, J. L. H. (2024). Streamlining academic document requests: Leveraging an online management system. In *2024 IEEE 12th Region 10 Humanitarian Technology Conference (R10-HTC)* (pp. 1-4). IEEE. https://doi.org/10.1109/R10-HTC59322.2024.10778819
- **Type:** RS, L. Peer-reviewed conference paper.
- **Verified:** Scholar query (run) returned it first. Crossref: title, seven authors, R10-HTC 2024, IEEE, pp. 1-4, October 2024. Abstract read on Semantic Scholar and OpenAlex. The Scholar snippet linked it to the Polytechnic University of the Philippines, but the abstract only says "a Philippine university" (see double-check list).
- **Scholar query (run):** `online document request system registrar Philippines`
- **Summary:** The authors built an online document request and tracking system for a Philippine university's registrar, aiming at better efficiency, security and collaboration in record keeping. They followed an SDLC method (planning, defining, designing, building, testing, deployment) and used HTML, CSS, JavaScript, PHP and MySQL on XAMPP. Respondents strongly agreed that the system met the ISO/IEC 25010 software quality characteristics.
- **Relevance:** This is the closest local registrar system to the thesis. It offers online requests and tracking with a PHP/MySQL stack, but its evaluation is based on user perception, and it has no event log analytics or prediction.
- **Section:** 2.4, 2.5

### 19. Abang et al. (2022), CCT online request of student credentials

- **APA:** Abang, K. I. R., Gatmaitan, D. L. V., Manalo, F. R., Torcelino, M. R., Rodriguez, R. L., & Serrano, E. A. (2022). CCT online request of students credentials: A document management system for private HIEs in the Philippines. In *2022 2nd International Conference in Information and Computing Research (iCORE)* (pp. 25-29). IEEE. https://doi.org/10.1109/iCORE58172.2022.00024
- **Type:** RS, L. Peer-reviewed conference paper.
- **Verified:** Scholar query (run) returned it second. Crossref: title (including the original "HIEs" spelling), six authors, iCORE 2022, IEEE, pp. 25-29, December 2022. Abstract read on Semantic Scholar.
- **Scholar query (run):** `online document request system registrar Philippines`
- **Summary:** The study replaced manual credential requests at City College of Tagaytay with an online request system. The motivation was growing request volume and the need to serve students safely during the COVID-19 pandemic. The system was built with an Agile method and first implemented at CCT, where it was found to be more convenient and safer for students. The authors plan to add features for other higher education institutions.
- **Relevance:** This is a local precedent for online credential requests in a Philippine college. Its evaluation is descriptive, with no measurement of turnaround or multi-office routing analytics.
- **Section:** 2.4, 2.5

### 20. Samson et al. (2025), BEYOND THE ARCH alumni record verification at UST

- **APA:** Samson, A. A. C., Abarquez, J. K. S. L., Llave, J. N. J., Sing, J. A. P., & Estrella, N. E. (2025). BEYOND THE ARCH: A web and mobile alumni record verification system for the University of Santo Tomas Office of the Registrar. *IET Conference Proceedings, 2024*(30), 56-65. https://doi.org/10.1049/icp.2025.0234
- **Type:** RS, L. Peer-reviewed conference proceedings (IET). Scholar lists it as an IEEE Xplore item from a green energy/technology conference (2024). Crossref lists it under IET Conference Proceedings, published March 2025.
- **Verified:** Scholar query (run) returned it. Crossref: title, five authors, IET Conference Proceedings vol. 2024 no. 30, pp. 56-65, 2025. Abstract read on OpenAlex.
- **Scholar query (run):** `online document request system registrar Philippines`
- **Summary:** The system moves alumni record verification at the UST Office of the Registrar online. The manual process had become a serious bottleneck during the pandemic. Features include document scanning, automated ticketing, certificate generation, user management, calendaring, and analytics for performance monitoring, linked to alumni records from 2004 onward. It was built with an iterative waterfall method and validated with unit and user acceptance testing. The authors report faster responses, better request tracking, and less administrative work, while noting that human cross-checking and data privacy compliance are still needed.
- **Relevance:** This is a local registrar system that, like the thesis, includes ticket tracking and performance analytics. It does not use process mining or predict delays.
- **Section:** 2.4, 2.5

### 21. Castro, Quilon and Arruejo (2022), DocTrack at the University of Northern Philippines

- **APA:** Castro, F., Quilon, E. R. C., & Arruejo, A. C. (2022). DocTrack: A dynamic document tracking system for the University of Northern Philippines. *Journal of Innovative Technology Convergence, 4*(2), 23-34. https://doi.org/10.69478/JITC2022v4n2a03
- **Type:** RS, L. Journal article (peer review status not confirmed; check the journal's policy).
- **Verified:** Crossref: title, journal, vol. 4 no. 2, pp. 23-34, 30 December 2022, publisher Innocon Publishing. Author names from OpenAlex (Crossref shows blank author slots, see double-check list). Abstract read on OpenAlex.
- **Scholar query:** `DocTrack dynamic document tracking system University of Northern Philippines`
- **Summary:** DocTrack is a web-based system that follows institutional documents from creation to approval and acts as a central document repository. It supports ISO-driven paperless transactions. The study used a descriptive-developmental design and a modified waterfall model. The system has six main functions: uploading a document for approval, tracking, viewing received documents, viewing documents pending release, viewing declined documents, and archiving. Security controls protect stored documents.
- **Relevance:** This is a local example of tracking a document through several approval stages, similar to the thesis's multi-office signing route. It records status but does no time analytics or prediction.
- **Section:** 2.4, 2.5

### 22. Hermosa (2023), document management with tracking at MSEUF Candelaria

- **APA:** Hermosa, K. R. (2023). Document management system with tracking and monitoring in Manuel S. Enverga University Foundation-Candelaria Inc. *International Journal of Advanced Research in Computer Science, 14*(6), 31-43. https://doi.org/10.26483/ijarcs.v14i6.7038
- **Type:** RS, L. Journal article (peer review status not confirmed).
- **Verified:** Crossref: title, journal, vol. 14 no. 6, pp. 31-43, 20 December 2023. Abstract read on OpenAlex. The abstract says "the researchers", which suggests more than one author, but Crossref and OpenAlex list only Hermosa, K. R. Check the PDF for the full author list.
- **Scholar query:** `document management system with tracking and monitoring Manuel S. Enverga University`
- **Summary:** The Dean's Office at MSEUF Candelaria worked across several platforms and kept losing track of documents moving between departments. The project built a central web workstation where offices send documents, collaborate, and monitor each document with a system-generated tracking code, then archive it once its cycle is done. It was developed with an Agile approach to allow close collaboration with the client offices.
- **Relevance:** This is a local precedent for inter-office document routing with tracking codes, a feature the thesis also has. Its evaluation is expectation-based, and it has no performance measurement.
- **Section:** 2.4

### 23. Farin (2022), QR code online document tracking in a Philippine HEI

- **APA:** Farin, M. N. (2022). Acceptability and usability of quick response code for on line document tracking in a higher education institution in the Philippines. *International Journal of Multidisciplinary Research and Analysis, 5*(1). https://doi.org/10.47191/ijmra/v5-i1-26
- **Type:** RS, L. Journal article (peer review status not confirmed). Page numbers were not in Crossref.
- **Verified:** Crossref: title, author, IJMRA vol. 5 no. 1, 27 January 2022, Everant Journals. Abstract read on OpenAlex.
- **Scholar query:** `acceptability and usability of quick response code online document tracking higher education institution Philippines`
- **Summary:** The study evaluated an online document tracking program that uses QR codes, with role-based logins. School heads and staff rated existing document tracking practices as only "Average" on most dimensions. They rated the QR-based tracking system "Excellent" on most dimensions and found it "Highly Accepted". Respondents said they were ready or very ready to adopt it.
- **Relevance:** This shows that Philippine HEI staff see manual document tracking as weak and accept digital tracking. It supports the need for the system, though it measures perception rather than turnaround.
- **Section:** 2.4

### 24. Lacorte (2026), online document request at Emilio Aguinaldo College

- **APA:** Lacorte, T. L. C. (2026). Enhancing registrar service delivery through an online document request system: A basis for process improvement. *International Journal of Sustainability and Advanced Integrated Research, 2*(2), 3788-3795. https://doi.org/10.65339/ijsair.V2.I2.635
- **Type:** RS, L. Journal article (peer review status not confirmed; this is a new journal).
- **Verified:** Scholar query (run) returned it. The DOI resolves to ijsair.com article 755. Crossref bibliographic search confirms the title, author, journal, 2026 date and pages 3788-3795. Abstract read on the journal page.
- **Scholar query (run):** `online document request system registrar Philippines`
- **Summary:** The study assessed the registrar's document request process at Emilio Aguinaldo College Cavite using SERVQUAL, lean management principles and e-service quality frameworks. It used a descriptive-evaluative design with 108 respondents (41 students, 56 alumni, 11 registrar staff), along with workflow, SWOT, PESTLE and multi-criteria analyses. Long processing time was the top complaint. About 69% of respondents supported going online, and a full online request system with real-time tracking and automatic notifications scored highest (4.50) among the options considered.
- **Relevance:** This is strong local evidence that long processing time is the main pain point in Philippine registrar services and that real-time tracking is valued. Both directly motivate the thesis's turnaround monitoring and prediction.
- **Section:** 2.4, 2.6

### 25. Macion, Brosas and Codilan (2026), EVSU Registrar e-Assist

- **APA:** Macion, M. J. M., Brosas, D. G., & Codilan, V. S. (2026). Eastern Visayas State University (EVSU) Registrar: e-Assist system. *International Journal of Sustainability and Advanced Integrated Research, 2*(2), 3065-3070. https://doi.org/10.65339/ijsair.V2.I2.546
- **Type:** RS, L. Journal article (peer review status not confirmed).
- **Verified:** Scholar query (run) returned it. The DOI resolves to ijsair.com article 650. Article page read (authors, volume, pages, abstract).
- **Scholar query (run):** `online document request system registrar Philippines`
- **Summary:** The study built and evaluated an automated registrar support system for Eastern Visayas State University, with AI chatbot features and self-service document requests. It combined interviews with an ISO/IEC 25010 evaluation by students, alumni and staff. Four problems were identified: limited resources, outdated manual processes, poor access to information, and growing demand for automation. The system received an overall weighted mean of 4.74.
- **Relevance:** This is a recent local registrar automation study tied to the SDGs, as the thesis is. Its evaluation is perception-based, and it has no process mining or turnaround prediction.
- **Section:** 2.4

### 26. Anasan et al. (2025), E-Credentials at King's College of the Philippines

- **APA:** Anasan, G., Basquial, K., Cadley, M., Camacho, M., Pascual, C., & Nginsayan, M. (2025). E-Credentials, online credential request for King's College of the Philippine. *Southeast Asian Journal of Science and Technology, 10*(1), 16-22. https://sajournal.pti.edu.ph/index.php/sajst/article/view/353
- **Type:** RS, L. Journal article (peer review status not confirmed). No DOI shown. The title is reproduced as published (it ends with "Philippine").
- **Verified:** Scholar query (run) returned it. Journal article page read.
- **Scholar query (run):** `online document request system registrar Philippines`
- **Summary:** The web system replaces manual credential requests at King's College of the Philippines. Students submit requests, pay online through PayMongo, and track status in real time, with role-based access for the registrar, accounting and admin staff. It was built in PHP/MySQL with Rapid Application Development and tested through unit and user acceptance testing. The PSSUQ usability evaluation gave an overall weighted mean of 4.52 ("Strongly Agree").
- **Relevance:** This is a local credential request system with payment and status tracking across registrar and accounting, close to the thesis's Cashier step. It has no event log analytics, bottleneck analysis or delay prediction.
- **Section:** 2.4, 2.5

### 27. Casicas, Castro, Macaindig and Labadan (2025), ODR.REGISTRAR-COC.EDU.PH

- **APA (as registered in DataCite):** Casicas, R. F. A., Castro, R. B., Macaindig, M. A., & Labadan, M. J. (2025). *ODR.REGISTRAR-COC.EDU.PH: Online document requisition system* [Unpublished capstone project, PHINMA Cagayan de Oro College]. ResearchGate. https://doi.org/10.13140/RG.2.2.13922.85448
- **Type:** RS, L. Capstone project (not peer-reviewed).
- **Verified:** The DOI resolves to ResearchGate. DataCite metadata: title "ODR.REGISTRAR-COC.EDU.PH: ONLINE DOCUMENT REQUISITION SYSTEM", creators Casicas, Renee Fe A.; Castro, Rex Bryan; Macaindig, Mohammad Alraffy; Labadan, Mark Justine; publicationYear **2025**; type Text, publisher "Unpublished". **Chapter 1 cites this as 2024; DataCite says 2025.** The ResearchGate page blocked automated access, so the abstract and the institution name could not be read. The institution comes from Chapter 1 and the "COC" domain.
- **Scholar query:** `ODR.REGISTRAR-COC.EDU.PH online document requisition system`
- **Summary:** Not summarised, because the abstract could not be accessed. From the title, it is an online document requisition system for a college registrar. The student should open the ResearchGate page and summarise it directly.
- **Relevance:** This is already cited in Chapter 1 as a local precedent for online registrar document requests.
- **Section:** 2.4

---

## E. Official policy documents: SDG 16.6, e-government, Philippine laws and CHED rules (Sections 2.1, 2.6)

### 28. United Nations, SDG 16 and Target 16.6

- **APA:** United Nations Department of Economic and Social Affairs. (n.d.). *Goal 16: Promote peaceful and inclusive societies for sustainable development, provide access to justice for all and build effective, accountable and inclusive institutions at all levels*. Retrieved October 12, 2026, from https://sdgs.un.org/goals/goal16
- **Type:** RL, F. Official UN document.
- **Verified:** sdgs.un.org/goals/goal16 opened. Target 16.6 reads "Develop effective, accountable and transparent institutions at all levels". Its indicators are 16.6.1 (primary government expenditures as a proportion of the original approved budget) and 16.6.2 (proportion of the population satisfied with their last experience of public services).
- **Scholar query:** not applicable (official site). Students can search `SDG 16.6 effective accountable transparent institutions`.
- **Summary:** SDG 16 asks countries to build effective, accountable and inclusive institutions. Target 16.6 focuses on making institutions effective, accountable and transparent at all levels. Indicator 16.6.2 measures this partly through how satisfied people are with their most recent use of a public service.
- **Relevance:** The thesis is aligned with SDG 16.6. The append-only event log adds accountability and transparency (every hand-off has a timestamp and a responsible office), and turnaround monitoring and prediction target the service effectiveness that shapes satisfaction under indicator 16.6.2.
- **Section:** 2.1 (context), 2.6

### 29. UN E-Government Survey 2024

- **APA:** United Nations Department of Economic and Social Affairs. (2024). *United Nations e-government survey 2024: Accelerating digital transformation for sustainable development, with the addendum on artificial intelligence*. United Nations. https://doi.org/10.18356/9789211067286
- **Type:** RL, F. Official UN report.
- **Verified:** Crossref: title "United Nations E-Government Survey 2024", subtitle "Accelerating Digital Transformation for Sustainable Development - With the addendum on Artificial Intelligence", author UN DESA, publisher United Nations, ISBN 9789211067286, 17 September 2024. The UN DESA publication page (desapublications.un.org/publications/un-e-government-survey-2024) was also read. As of October 2026 the 2026 edition is still being prepared (a search showed UN DESA starting the 2026 assessment round), so **2024 is the latest published edition**. If the 2026 edition comes out before the defense, replace this source with it.
- **Scholar query:** `United Nations E-Government Survey 2024`
- **Summary:** The 13th edition assessed digital government in all 193 UN Member States using the E-Government Development Index (EGDI). It reports that the share of the world's population lagging in digital government development fell from 45.0% in 2022 to 22.4% in 2024. Africa, the least developed countries and small island developing States remained below the global average, and local (city) e-government continues to lag behind national e-government. The edition introduces a Digital Government Model Framework and adds a section on artificial intelligence in government.
- **Relevance:** This gives the global context for moving institutional services online and for responsible use of AI and analytics in public-facing services, the setting in which the thesis's predictive feature sits.
- **Section:** 2.1, 2.6

### 30. Republic Act No. 11032 (2018), Ease of Doing Business and Efficient Government Service Delivery Act

- **APA:** Ease of Doing Business and Efficient Government Service Delivery Act of 2018, Republic Act No. 11032 (2018). https://lawphil.net/statutes/repacts/ra2018/ra_11032_2018.html
- **Type:** RL, L. Philippine law.
- **Verified:** The full text was read on lawphil.net (Approved: May 28, 2018). The Official Gazette page (officialgazette.gov.ph/2018/05/28/republic-act-no-11032/) and the ARTA page returned HTTP 403 to automated access, so students should open the Official Gazette version in a normal browser and cite that URL if the department prefers official sites.
- **Scholar query:** not applicable. Search `Republic Act 11032 ease of doing business processing time`.
- **Summary:** RA 11032 amends the Anti-Red Tape Act of 2007 (RA 9485) and declares a state policy of efficient turnaround in government service delivery. It requires every covered office to publish a Citizen's Charter with its service standards (Sec. 6). It caps processing time at **3 working days for simple transactions, 7 working days for complex transactions, and 20 working days for highly technical applications** (Sec. 9). It limits signatories to at most three and allows electronic signatures. It provides for automatic approval if an office misses the prescribed time (Sec. 10), and it creates the Anti-Red Tape Authority (Sec. 17). Coverage (Sec. 3) is government offices, agencies, LGUs and GOCCs.
- **Relevance:** This is the national benchmark for turnaround standards and due dates, and it inspires the SLA/due-date logic in the system. Note: UPHSL is a private HEI, so RA 11032 does not bind it directly. The thesis should say it uses RA 11032 as a reference standard for efficient service delivery, not as a legal requirement.
- **Section:** 2.1, 2.6

### 31. Republic Act No. 10173 (2012), Data Privacy Act

- **APA:** Data Privacy Act of 2012, Republic Act No. 10173 (2012). https://privacy.gov.ph/data-privacy-act/
- **Type:** RL, L. Philippine law.
- **Verified:** The full text was read on lawphil.net (lawphil.net/statutes/repacts/ra2012/ra_10173_2012.html, Approved: August 15, 2012). The official National Privacy Commission page (privacy.gov.ph/data-privacy-act/) loaded normally (HTTP 200).
- **Scholar query:** not applicable. Search `Republic Act 10173 Data Privacy Act of 2012`.
- **Summary:** The Act protects personal information in both government and private-sector information systems and creates the National Privacy Commission. It sets general data privacy principles (Sec. 11), criteria for lawful processing (Sec. 12), the rights of data subjects (Sec. 16), the security of personal information (Sec. 20), and the accountability of personal information controllers (Sec. 21). Importantly, Sec. 3(l) defines information about an individual's **education** as *sensitive personal information*, which gets stricter protection under Sec. 13.
- **Relevance:** Academic credentials and student records are sensitive personal information under this Act. This justifies the system's role-based access, the minimal data kept in the event log, and the handling of uploaded proof of payment.
- **Section:** 2.1, 2.6

### 32. CHED Memorandum Order No. 40, s. 2008, Manual of Regulations for Private Higher Education (MORPHE)

- **APA:** Commission on Higher Education. (2008). *Manual of regulations for private higher education of 2008* (CHED Memorandum Order No. 40, s. 2008). https://legacy.ched.gov.ph/wp-content/uploads/2017/10/CMO-No.40-s2008.pdf
- **Type:** RL, L. Official policy document (government regulation for private HEIs).
- **Verified:** The PDF was downloaded from the official CHED site (linked from legacy.ched.gov.ph/2008-ched-memorandum-orders/). The cover page (CMO No. 40, s. 2008, signed 31 July 2008 by Acting Chairman Romulo L. Neri, adopted by Commission en banc Resolution No. 398-2008) and Article XX were read directly from the scanned pages.
- **Scholar query:** not applicable. Search `CHED Memorandum Order 40 series 2008 Manual of Regulations for Private Higher Education`.
- **Summary:** MORPHE applies to all private higher education institutions under CHED. Article XX (School Records and Transfer of Students) states the following. A student's school record holds final ratings and credits (Sec. 94). A transfer credential must be signed by the Registrar and issued **within two weeks** of the application (Sec. 95). A school asked for records must forward them **within thirty days** (Sec. 96). An HEI must release the records of a student who has no outstanding property or financial obligations and is not under suspension or expulsion (Sec. 97). An HEI may withhold transfer credentials while such obligations remain, and CHED may order release if records are refused without good reason (Sec. 98).
- **Relevance:** This is the key regulatory basis for the system's routing. Clearance from the Library (property obligations) and the Cashier/Finance office (financial obligations) before Registrar release follows directly from Secs. 97 and 98. The two-week and thirty-day limits are official due dates the SLA flag can use.
- **Section:** 2.1, 2.5, 2.6

---

## F. Comparison of existing systems and approaches (Section 2.5)

"Yes", "Partial" and "No" are based only on what the abstract or full text actually describes. If a feature is not mentioned, it is marked "Not reported".

| # | Study / system | Domain | Request tracking | Multi-office routing | Event log / process mining | Prediction | Evaluation metric | Gap compared with this thesis |
|---|---|---|---|---|---|---|---|---|
| 18 | Velasco et al. (2024) | PH university registrar | Yes | Not reported | No | No | ISO/IEC 25010 user ratings | No per-office timing, no event log analytics, no prediction |
| 19 | Abang et al. (2022), CCT | PH college credentials | Partial (online request) | Not reported | No | No | Descriptive (convenience, safety) | No turnaround measurement or routing analysis |
| 20 | Samson et al. (2025), UST | PH registrar, alumni verification | Yes (ticketing) | Not reported | No (has performance analytics dashboard) | No | Unit testing and UAT | Analytics are descriptive; no bottleneck discovery or delay prediction |
| 21 | Castro et al. (2022), DocTrack UNP | PH university document approval | Yes | Yes (approval stages) | No | No | Descriptive-developmental | Status tracking only; no time analytics |
| 22 | Hermosa (2023), MSEUF | PH inter-office documents | Yes (tracking code) | Yes (departments) | No | No | Expected outcomes (not measured) | No performance measurement |
| 24 | Lacorte (2026), EAC | PH registrar process improvement | Proposed | Not reported | No | No | SERVQUAL, MCDA (4.50 score) | Proposal stage; names long processing time but does not model it |
| 25 | Macion et al. (2026), EVSU | PH registrar automation | Yes (self-service) | Not reported | No | No | ISO/IEC 25010 (4.74) | Chatbot focus; no process mining or turnaround prediction |
| 26 | Anasan et al. (2025), KCP | PH credential request | Yes (real-time status) | Partial (registrar, accounting) | No | No | PSSUQ (4.52) | No event log analytics, bottleneck analysis or SLA prediction |
| 14 | Caballero-Hernández et al. (2018) | Foreign university curriculum | No | No | Yes (bottleneck discovery) | No | Frequency comparison of course completion time | Academic progress, not administrative services; no prediction |
| 15 | Atastina and Kurniati (2014) | Foreign student registration | No | Not reported | Yes | Not reported | Not read (abstract unavailable) | Offline analysis of a past log; no live system |
| 10 | van der Aalst et al. (2011) | Foreign municipal services | No (analysis tool) | Yes (process steps) | Yes (annotated transition system) | Yes (completion time) | MAE, RMSE, MAPE in days | Offline research tool in ProM; not built into a request system; no SLA flag |
| 7 | Verenich et al. (2019) | Foreign multi-domain benchmark | No | Yes (logs) | Yes | Yes (remaining time) | MAE in days | Benchmark only; no deployed system; no registrar data |
| 8 | Teinemaa et al. (2019) | Foreign multi-domain benchmark | No | Yes (logs) | Yes | Yes (outcome / class) | AUC, F-score | Benchmark only; outcome classification, not turnaround days |
| 12 | Metzger et al. (2015) | Foreign logistics | No | Yes | Partial | Yes (violation) | Accuracy, precision, recall | Industrial logistics; not education or public service |
| This thesis | UPHSL credential tracker | PH private HEI registrar | Yes | Yes (Library, Cashier, Dean, Registrar) | Yes (append-only log, discovery, bottlenecks) | Yes (Ridge regression for days, likely range, late probability) | MAE/RMSE against an average-based baseline | Combines all of the above in one deployed system |

---

## G. ML benchmarks table (Section 2.5, "ML benchmarks")

All numbers come from the full texts actually read. Units: MAE in days unless stated. The Verenich et al. numbers come from the arXiv v2 preprint (Table 10, weighted average MAE over all prefixes) and may differ slightly in the published ACM version.

| Method | Dataset (domain) | Metric | Reported result | Source |
|---|---|---|---|---|
| LSTM | BPIC 2012 A (loan applications, finance) | MAE, days | 3.772 | Verenich et al. (2019), arXiv Table 10 |
| Transition system (van der Aalst et al., 2011) | BPIC 2012 A | MAE, days | 8.363 | Verenich et al. (2019) |
| LSTM | Helpdesk (IT ticketing) | MAE, days | 3.458 | Verenich et al. (2019) |
| Transition system | Helpdesk | MAE, days | 6.124 | Verenich et al. (2019) |
| No bucketing + last-state encoding (XGBoost) | BPIC 2015 municipality 1 (building permits, public administration) | MAE, days | 35.451 (best on this log) | Verenich et al. (2019) |
| LSTM | BPIC 2015 municipality 1 | MAE, days | 39.457 | Verenich et al. (2019) |
| Transition system | BPIC 2015 municipality 1 | MAE, days | 56.498 | Verenich et al. (2019) |
| LSTM | Road traffic fines (public administration) | MAE, days | 178.738 | Verenich et al. (2019) |
| Transition system | Road traffic fines | MAE, days | 190.949 | Verenich et al. (2019) |
| Overall finding | 16 real-life logs | MAE, days | LSTM best on 13 of 16 logs; no significant difference among bucketing/encoding combinations | Verenich et al. (2019) |
| LSTM (best configuration) | Helpdesk | MAE of time to next event, days (all prefixes) | about 3.75 vs 5.67 to 5.83 for transition-system baselines | Tax et al. (2017), Table 1 |
| LSTM (best configuration) | BPIC 2012 W | MAE of time to next event, days (all prefixes) | about 1.56 vs 1.91 to 1.97 for baselines | Tax et al. (2017), Table 1 |
| LSTM | Helpdesk / BPIC 2012 W | Next-activity accuracy | up to 0.7123 / 0.7600 | Tax et al. (2017), Table 1 |
| Annotated transition system, set abstraction | Dutch municipality WMO requests (796 cases) | MAE / RMSE, days | 63.805 / 74.947 | van der Aalst et al. (2011), Table 4 |
| Simple heuristic (half of average flow time) | Same WMO log | MAE / RMSE, days | 80.823 / 101.342 | van der Aalst et al. (2011), Table 4 |
| XGBoost | 24 outcome tasks from 9 logs | AUC | highest AUC in 15 of 24 tasks; logistic regression not significantly worse | Teinemaa et al. (2019) |
| ML, constraint satisfaction, QoS aggregation | Transport and logistics case | Accuracy at half of process duration | at least 70% for all; combos improve precision by 14% and recall by 23% | Metzger et al. (2015) |

How to use this table in the thesis: the WMO result (process-aware model beats an average-based heuristic, about 64 vs 81 days MAE) is the closest analogue to the thesis's own test (Ridge regression vs average-based reporting). The thesis should report MAE in days with a temporal train/test split so its numbers can be compared in kind with these benchmarks. They cannot be compared directly, because registrar turnaround is measured in days over a much shorter process.

---

## H. Synthesis and research gap (Section 2.6)

- **Local systems stop at digitising the request.** All the Philippine studies found (Velasco et al., 2024; Abang et al., 2022; Anasan et al., 2025; Macion et al., 2026; Castro et al., 2022; Hermosa, 2023; Farin, 2022) move requests or documents online and show status. None of them records a timestamped hand-off log suitable for process mining, and none predicts turnaround.
- **Local evaluations measure perception, not performance.** Local systems are judged mostly with ISO/IEC 25010, PSSUQ or Likert acceptability scores. Lacorte (2026) names long processing time as the top complaint but does not measure or model it. This thesis measures turnaround directly from event data.
- **Process mining in education focuses on learning, not administration.** Educational process mining reviews (Bogarín et al., 2018) and studies such as Caballero-Hernández et al. (2018) deal mainly with curricula and learning behaviour. Administrative cases such as student registration (Atastina & Kurniati, 2014) are fewer, and none was found for multi-office credential release in the Philippines.
- **Cross-unit, physical-digital services are understudied.** Thiede et al. (2018) found that process mining across systems or organisational units, and of services that mix physical and digital steps, is underrepresented. Credential release (online tracking plus physical clearance and document release across four offices) is exactly that kind of service.
- **Predictive monitoring research is benchmark-heavy and seldom deployed.** Benchmarks (Verenich et al., 2019; Teinemaa et al., 2019; Tax et al., 2017) show that accurate time and outcome prediction is possible, but they evaluate offline on public logs, not inside a working request system with a live SLA flag. Schröer et al. (2021) similarly note that the deployment phase is often missing.
- **Simple, interpretable models are a defensible choice.** Verenich et al. (2019) found no significant difference among many classical encodings, Teinemaa et al. (2019) found logistic regression statistically competitive, and van der Aalst et al. (2011) showed process-aware models beat average-based heuristics. Together these support a Ridge regression (Hoerl & Kennard, 1970) that registrar staff can understand, evaluated against average-based reporting.
- **Policy sets the due dates and routing.** CHED MORPHE (CMO 40, s. 2008) ties release of records to clearing property and financial obligations, which is why the Library and Cashier steps exist, and sets two-week and thirty-day limits. RA 11032 sets 3/7/20 working-day standards for government services. RA 10173 classes education records as sensitive personal information, and SDG 16.6 calls for effective, accountable, transparent institutions. No local system found turns these standards into measured, predicted and flagged due dates.
- **The gap this thesis fills:** an integrated, deployed system for a Philippine private HEI registrar that (1) routes each credential request through Library, Cashier, Dean and Registrar, (2) records every hand-off in an append-only event log, (3) applies process mining for discovery and bottleneck detection, and (4) predicts turnaround days with a likely range and a probability of breaching the due date, evaluated against average-based reporting.

---

## I. Items that could not be verified or that the student should double-check

1. **Caballero-Hernández et al. (2018) author list.** Crossref lists **six** authors (adds Palomo-Duarte, M. and Argudo, J. F.). Chapter 1 lists four. Fix Chapter 1.
2. **Casicas et al. year.** DataCite gives publicationYear **2025**. Chapter 1 says 2024. The abstract and institution could not be read (ResearchGate blocked automated access). Open the ResearchGate page, confirm the year, and write the summary yourself.
3. **Fayyad et al. (1996) pages 37-54.** This is the standard cited range, but it was not re-checked on the publisher PDF. The DOI resolves to the AAAI page.
4. **Wirth and Hipp (2000).** Title, authors and venue confirmed by web search only. The page range 29-39 and the year (one source cites a 1999 version) were not confirmed from a primary copy. The CRISP-DM 1.0 step-by-step guide by Chapman et al. (2000) was **not** verified and is not listed.
5. **Verenich et al. (2019) numbers.** These come from arXiv v2 (16 datasets). The published ACM version mentions 17 datasets, so check Table 10 in the ACM version before quoting exact MAE values.
6. **Editors and volume numbers.** The editors and volume numbers of the CAiSE 2017 proceedings (Tax et al.: Dubois & Pohl, LNCS 10253) and the BPM Workshops 2011 proceedings (Process Mining Manifesto: Daniel, Barkaoui & Dustdar, LNBIP 99) were added from general knowledge and are not in the Crossref output. Confirm them on the Springer pages, or drop the editor and volume details.
7. **Atastina and Kurniati (2014).** Metadata verified, but no abstract was available. Read the paper before citing specific findings.
8. **Velasco et al. (2024) institution.** Google Scholar's snippet suggested the Polytechnic University of the Philippines, but the abstract only says "a Philippine university". Check the paper before naming the school.
9. **Hermosa (2023) authors.** The abstract says "the researchers", but metadata lists one author. Check the PDF.
10. **DocTrack (Castro et al., 2022) authors.** Crossref shows blank author slots. Names were taken from OpenAlex. Check the PDF.
11. **Peer review status.** IJSAIR (Lacorte; Macion et al.), SAJST (Anasan et al.), IJMRA (Farin), IJARCS (Hermosa) and JITC (Castro et al.) were not checked for peer review policy. Check each journal's "About" page if your adviser requires peer-reviewed sources only.
12. **RA 11032 official URL.** The Official Gazette and ARTA pages blocked automated access (HTTP 403). The text was read on lawphil.net. Open the Official Gazette page (https://www.officialgazette.gov.ph/2018/05/28/republic-act-no-11032/) in a browser if you prefer citing the official site. Remember that RA 11032 covers government offices, so for a private HEI cite it as a reference standard, not a binding rule.
13. **UN E-Government Survey edition.** The 2024 edition is the latest published one as of October 2026. Replace it with the 2026 edition if it comes out before the defense.
14. **Dropped / not included:** Chapman et al. (2000) CRISP-DM 1.0 guide (not verified); Di Francescomarino and Ghidini (2022) "Predictive process monitoring" chapter (DOI 10.1007/978-3-031-08848-3_10 verified, but the content was not read, so it was left out); Leitner et al. (2010) SLA violation prediction (DOI 10.1007/978-3-642-16132-2_17 verified, but no abstract was available, so it was left out; add it after reading if you need a second SLA source); Baban and Mokhtar (2010) online document management system (verified, but only loosely related); the GMA Civil Registrar and eDokumento studies (found on Scholar, but full metadata could not be confirmed); the DICT E-Government Master Plan (not checked).
15. **Google Scholar "(run)" marks.** Only queries marked "(run)" were actually run on Scholar before the CAPTCHA block. Run the others yourself to take screenshots for your instructor.

---

## Source count

- Total verified sources listed: **33** (entries 1-32 plus 6a). Entry 27 (Casicas) has verified metadata but no summary. Entry 15 (Atastina) has verified metadata but only a limited summary. Entry 6a (Wirth & Hipp) is partly verified.
- **Related Literature: 11.** Entries 1, 2, 3, 4, 6, 6a, 28, 29, 30, 31, 32. Of these, 8 are foreign (1, 2, 3, 4, 6, 6a, 28, 29) and 3 are local (30, 31, 32).
- **Related Studies: 22.** Entries 5 and 7-27. Of these, 12 are foreign (5, 7-17) and 10 are local (18-27).
- **Foreign: 20. Local (Philippine): 13.**
- **Strongly relevant to this system (at least 15 needed):** entries 1, 2, 7, 8, 10, 12, 14, 16, 18, 19, 20, 21, 24, 26, 30, 31 and 32 (17 sources).
