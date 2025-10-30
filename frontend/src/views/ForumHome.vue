<script setup>
import ForumHeader from '../components/ForumHeader.vue';
</script>

<template>
    <ForumHeader />
    <div class="forum-home py-3">
        <div class="container-xl">
            <div class="row g-4">
                <!-- Left Sidebar -->
                <div class="col-12 col-lg-3 order-2 order-lg-1">
                    <div class="card border-0 shadow-sm rounded-2 mb-3 profile-card">
                        <div class="card-body d-flex gap-3 align-items-center">
                            <div class="avatar bg-secondary-subtle rounded-circle flex-shrink-0"></div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold text-dark">Joe Hornet</div>
                                <div class="small text-secondary">Posts: <b>23</b> &nbsp; Likes: <b>235</b> &nbsp; Comments: <b>59</b></div>
                            </div>
                        </div>
                    </div>

                    <button class="btn btn-success w-100 mb-3 create-post-btn">
                        CREATE
                        <span class="d-none d-sm-inline"> POST</span>
                    </button>

                    <div class="card border-0 shadow-sm rounded-2">
                        <div class="card-body">
                            <div class="fw-semibold mb-2">Filter By Tags</div>
                            <div class="d-flex flex-column gap-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="tagEducation">
                                    <label class="form-check-label" for="tagEducation"><span class="badge rounded-pill tag-badge">Education</span></label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="tagInformational">
                                    <label class="form-check-label" for="tagInformational"><span class="badge rounded-pill tag-badge">Informational</span></label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="tagEvents">
                                    <label class="form-check-label" for="tagEvents"><span class="badge rounded-pill tag-badge">Events</span></label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="tagQuestion">
                                    <label class="form-check-label" for="tagQuestion"><span class="badge rounded-pill tag-badge">Question</span></label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="tagData">
                                    <label class="form-check-label" for="tagData"><span class="badge rounded-pill tag-badge">Data Analysis</span></label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="tagResearch">
                                    <label class="form-check-label" for="tagResearch"><span class="badge rounded-pill tag-badge">Research</span></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-12 col-lg-9 order-1 order-lg-2">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="small text-secondary">7 posts</div>
                        <div class="d-flex align-items-center gap-2 small">
                            <span class="text-secondary">Sort by:</span>
                            <div class="sort-pill">Latest</div>
                        </div>
                    </div>

                    <!-- Section: Official Announcements -->
                    <div class="section mb-4">
                        <div class="section-title">Official Announcements & Updates</div>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item px-0 py-3">
                                <div class="d-flex align-items-start gap-3">
                                    <i class="pi pi-megaphone text-secondary fs-5"></i>
                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <a href="#" class="post-link">Managing California’s Groundwater</a>
                                            <span class="badge text-bg-warning rounded-pill small fw-semibold ms-1">Official</span>
                                        </div>
                                        <div class="d-flex flex-wrap gap-1 mt-2">
                                            <span class="badge rounded-pill tag-badge">Education</span>
                                            <span class="badge rounded-pill tag-badge">Informational</span>
                                            <span class="badge rounded-pill tag-badge">Events</span>
                                            <span class="badge rounded-pill tag-badge">Networking</span>
                                        </div>
                                        <div class="small text-secondary mt-1">Closed comments</div>
                                    </div>
                                    <div class="text-nowrap text-end small text-secondary">
                                        <div class="mb-1">2 hrs ago</div>
                                        <div class="d-flex align-items-center gap-1 justify-content-end"><i class="pi pi-user"></i><span>OWP Administration</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Design & Operations -->
                    <div class="section mb-4">
                        <div class="section-title">Design & Operations</div>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item px-0 py-3">
                                <div class="d-flex align-items-start gap-3">
                                    <i class="pi pi-book text-secondary fs-5"></i>
                                    <div class="flex-grow-1">
                                        <a href="#" class="post-link">Upgrading old and outdated equipment</a>
                                        <div class="d-flex flex-wrap gap-1 mt-2">
                                            <span class="badge rounded-pill tag-badge">Education</span>
                                            <span class="badge rounded-pill tag-badge">Informational</span>
                                            <span class="badge rounded-pill tag-badge">Events</span>
                                            <span class="badge rounded-pill tag-badge">Networking</span>
                                        </div>
                                        <div class="small text-secondary mt-1">4 comments</div>
                                    </div>
                                    <div class="text-nowrap text-end small text-secondary">
                                        <div class="mb-1">3 days ago</div>
                                        <div class="d-flex align-items-center gap-1 justify-content-end"><i class="pi pi-user"></i><span>Joe Smith</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Research -->
                    <div class="section mb-4">
                        <div class="section-title">Research</div>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item px-0 py-3">
                                <div class="d-flex align-items-start gap-3">
                                    <i class="pi pi-chart-line text-secondary fs-5"></i>
                                    <div class="flex-grow-1">
                                        <a href="#" class="post-link">Real-time water quality monitoring</a>
                                        <div class="d-flex flex-wrap gap-1 mt-2">
                                            <span class="badge rounded-pill tag-badge">Research</span>
                                            <span class="badge rounded-pill tag-badge">Data Analysis</span>
                                        </div>
                                        <div class="small text-secondary mt-1">8 comments</div>
                                    </div>
                                    <div class="text-nowrap text-end small text-secondary">
                                        <div class="mb-1">3 weeks ago</div>
                                        <div class="d-flex align-items-center gap-1 justify-content-end"><i class="pi pi-user"></i><span>Johnny Hornet</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Questions -->
                    <div class="section mb-4">
                        <div class="section-title">Questions</div>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item px-0 py-3">
                                <div class="d-flex align-items-start gap-3">
                                    <i class="pi pi-question-circle text-secondary fs-5"></i>
                                    <div class="flex-grow-1">
                                        <a href="#" class="post-link">How to use SCADA</a>
                                        <div class="d-flex flex-wrap gap-1 mt-2">
                                            <span class="badge rounded-pill tag-badge">Education</span>
                                            <span class="badge rounded-pill tag-badge">Informational</span>
                                        </div>
                                        <div class="small text-secondary mt-1">1 comment</div>
                                    </div>
                                    <div class="text-nowrap text-end small text-secondary">
                                        <div class="mb-1">10 mins. ago</div>
                                        <div class="d-flex align-items-center gap-1 justify-content-end"><i class="pi pi-user"></i><span>Donald McRonald</span></div>
                                    </div>
                                </div>
                            </div>

                            <div class="list-group-item px-0 py-3">
                                <div class="d-flex align-items-start gap-3">
                                    <i class="pi pi-question-circle text-secondary fs-5"></i>
                                    <div class="flex-grow-1">
                                        <a href="#" class="post-link">How to effectively run a water treatment plant</a>
                                        <div class="d-flex flex-wrap gap-1 mt-2">
                                            <span class="badge rounded-pill tag-badge">Education</span>
                                            <span class="badge rounded-pill tag-badge">Informational</span>
                                            <span class="badge rounded-pill tag-badge">Question</span>
                                        </div>
                                        <div class="small text-secondary mt-1">5 comments</div>
                                    </div>
                                    <div class="text-nowrap text-end small text-secondary">
                                        <div class="mb-1">1 day ago</div>
                                        <div class="d-flex align-items-center gap-1 justify-content-end"><i class="pi pi-user"></i><span>Timothy Hornet</span></div>
                                    </div>
                                </div>
                            </div>

                            <div class="list-group-item px-0 py-3">
                                <div class="d-flex align-items-start gap-3">
                                    <i class="pi pi-question-circle text-secondary fs-5"></i>
                                    <div class="flex-grow-1">
                                        <a href="#" class="post-link">How to properly measure and verify chemical readings</a>
                                        <div class="d-flex flex-wrap gap-1 mt-2">
                                            <span class="badge rounded-pill tag-badge">Education</span>
                                            <span class="badge rounded-pill tag-badge">Informational</span>
                                        </div>
                                        <div class="small text-secondary mt-1">29 comments</div>
                                    </div>
                                    <div class="text-nowrap text-end small text-secondary">
                                        <div class="mb-1">1 week ago</div>
                                        <div class="d-flex align-items-center gap-1 justify-content-end"><i class="pi pi-user"></i><span>Gerald Hornet</span></div>
                                    </div>
                                </div>
                            </div>

                            <div class="list-group-item px-0 py-3">
                                <div class="d-flex align-items-start gap-3">
                                    <i class="pi pi-question-circle text-secondary fs-5"></i>
                                    <div class="flex-grow-1">
                                        <a href="#" class="post-link">Where can I get information on properly maintaining and calibrating instruments...</a>
                                        <div class="d-flex flex-wrap gap-1 mt-2">
                                            <span class="badge rounded-pill tag-badge">Education</span>
                                            <span class="badge rounded-pill tag-badge">Informational</span>
                                        </div>
                                        <div class="small text-secondary mt-1">42 comments</div>
                                    </div>
                                    <div class="text-nowrap text-end small text-secondary">
                                        <div class="mb-1">1 mo. ago</div>
                                        <div class="d-flex align-items-center gap-1 justify-content-end"><i class="pi pi-user"></i><span>Victoria Smith</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.forum-home { background: #F0F2F3; }

.profile-card .avatar { width: 56px; height: 56px; }

.create-post-btn { background:#2E6C44; border-color:#2E6C44; font-weight:700; }
.create-post-btn:hover { background:#275d3a; border-color:#275d3a; }

.tag-badge { background:#E7EFE5; color:#2E6C44; font-weight:600; }

.sort-pill { background:#E7EFE5; color:#2E6C44; padding:4px 10px; border-radius:12px; font-weight:600; }

.section-title { display:inline-block; background:#2E6C44; color:#fff; padding:6px 12px; border-radius:8px; font-weight:700; font-size:0.95rem; }

.post-link { color:#2B2B2B; text-decoration:none; font-weight:600; }
.post-link:hover { text-decoration:underline; }

.list-group-item { border: 0; border-bottom:1px solid #E0E0E0; background:transparent; }
</style>