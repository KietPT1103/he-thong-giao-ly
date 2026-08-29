<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, type Component } from "vue";
import { useAuthStore } from "../stores/authStore";
import { dashboardFor } from "../router";
import {
    ArrowRight,
    BarChart3,
    Bell,
    BookOpen,
    CalendarDays,
    Check,
    ChevronRight,
    Church,
    ClipboardCheck,
    Clock3,
    Facebook,
    GraduationCap,
    LocateFixed,
    LogIn,
    Mail,
    MapPin,
    Menu,
    Phone,
    Play,
    Star,
    TrendingUp,
    Users,
    X,
    Youtube,
} from "lucide-vue-next";

type Feature = {
    icon: Component;
    title: string;
    text: string;
    tone: string;
    color: string;
};

const nav = [
    ["#trang-chu", "Trang chủ"],
    ["#gioi-thieu", "Giới thiệu"],
    ["#chuc-nang", "Chức năng"],
    ["#doi-tuong", "Đối tượng"],
    ["#tin-tuc", "Tin tức"],
    ["#lien-he", "Liên hệ"],
];
const stats = [
    {
        icon: Users,
        value: "56",
        label: "Giáo lý viên",
        note: "Đồng hành & phục vụ",
    },
    { icon: BookOpen, value: "32", label: "Lớp học", note: "Đang hoạt động" },
    {
        icon: GraduationCap,
        value: "768",
        label: "Thiếu nhi",
        note: "Đang tham gia học",
    },
    { icon: Church, value: "1", label: "Giáo xứ", note: "Cái Răng" },
];
const features: Feature[] = [
    {
        icon: ClipboardCheck,
        title: "Điểm danh",
        text: "Điểm danh nhanh chóng, chính xác bằng QR code",
        tone: "#edf5ff",
        color: "#1170df",
    },
    {
        icon: BookOpen,
        title: "Bài tập",
        text: "Giao bài, nộp bài và chấm điểm dễ dàng",
        tone: "#edf5ff",
        color: "#1267ce",
    },
    {
        icon: CalendarDays,
        title: "Lịch học",
        text: "Quản lý lịch học khoa học, rõ ràng",
        tone: "#edf5ff",
        color: "#207ce7",
    },
    {
        icon: BarChart3,
        title: "Theo dõi tiến độ",
        text: "Theo dõi sát tiến trình học tập và sự tiến bộ",
        tone: "#edf5ff",
        color: "#5b9df1",
    },
    {
        icon: Bell,
        title: "Thông báo",
        text: "Gửi thông báo nhanh đến lớp, giáo lý viên & phụ huynh",
        tone: "#edf5ff",
        color: "#176bd4",
    },
    {
        icon: Star,
        title: "Tích điểm",
        text: "Khuyến khích và ghi nhận sự cố gắng mỗi ngày",
        tone: "#fff8e7",
        color: "#f2a915",
    },
];
const audiences = [
    {
        avatar: "/images/02_individual_assets/role-admin-parish.png",
        title: "Admin Giáo xứ",
        items: [
            "Quản lý toàn hệ thống",
            "Báo cáo và thống kê",
            "Phân quyền linh hoạt",
        ],
    },
    {
        avatar: "/images/02_individual_assets/role-catechist.png",
        title: "Giáo lý viên",
        items: [
            "Quản lý lớp học",
            "Điểm danh & giao bài",
            "Theo dõi tiến độ học tập",
        ],
    },
    {
        avatar: "/images/02_individual_assets/role-parents.png",
        title: "Phụ huynh",
        items: [
            "Theo dõi con",
            "Xem điểm & nhận xét",
            "Phối hợp cùng giáo lý viên",
        ],
    },
    {
        avatar: "/images/02_individual_assets/role-child.png",
        title: "Thiếu nhi",
        items: [
            "Xem lịch học & bài",
            "Xem điểm & nhận thưởng",
            "Tích điểm – nhận thưởng",
        ],
    },
];
const steps = [
    {
        no: "1",
        icon: ClipboardCheck,
        title: "Đăng ký tài khoản",
        text: "Đăng ký tài khoản và tham gia nhóm chung",
        color: "#0864cc",
    },
    {
        no: "2",
        icon: Users,
        title: "Tham gia lớp học",
        text: "Tham gia lớp và cập nhật thông tin cá nhân",
        color: "#ffab13",
    },
    {
        no: "3",
        icon: TrendingUp,
        title: "Học tập & đồng hành",
        text: "Học tập đều đặn, nhận thông báo và cùng nhau lớn lên",
        color: "#4d8e3d",
    },
];
const events = [
    {
        day: "26",
        month: "THÁNG 5",
        title: "Thánh Lễ Tĩnh Tâm, Kết Năm",
        time: "08:00 - 10:30",
        place: "Nhà thờ Cái Răng",
    },
    {
        day: "02",
        month: "THÁNG 6",
        title: "Sinh hoạt lớp Giáo Lý",
        time: "14:00 - 16:00",
        place: "Các lớp học Giáo lý",
    },
    {
        day: "16",
        month: "THÁNG 6",
        title: "Ngày Hội Thiếu Nhi",
        time: "07:30 - 16:00",
        place: "Sân Giáo xứ",
    },
];
const notices = [
    {
        icon: Church,
        title: "Thánh lễ cuối tuần",
        text: "Thứ Bảy: 17:30 · Chúa Nhật: 06:00, 08:30, 17:30 tại Nhà thờ Giáo xứ.",
        tone: "#eaf4ff",
        color: "#2f86e6",
        badge: "Mới",
    },
    {
        icon: ClipboardCheck,
        title: "Cập nhật bài tập mới",
        text: "Giáo lý viên đã cập nhật bài tập tuần 7 cho các lớp. Xem ngay!",
        tone: "#e9f8f1",
        color: "#42a875",
    },
    {
        icon: Bell,
        title: "Thánh Lễ Tĩnh Tâm",
        text: "Kính mời quý phụ huynh và các lớp tham dự Thánh Lễ.",
        tone: "#fff7e8",
        color: "#e9a11a",
    },
];

const open = ref(false);
const auth = useAuthStore();
const systemDestination = computed(() =>
    auth.isAuthenticated ? dashboardFor(auth.roles) : "/login",
);
const systemLabel = computed(() =>
    auth.isAuthenticated ? "Vào hệ thống" : "Đăng nhập",
);
function toggle(value: boolean) {
    open.value = value;
    document.body.style.overflow = value ? "hidden" : "";
}
onMounted(() => window.addEventListener("keydown", onKey));
onBeforeUnmount(() => {
    window.removeEventListener("keydown", onKey);
    document.body.style.overflow = "";
});
function onKey(event: KeyboardEvent) {
    if (event.key === "Escape") toggle(false);
}
</script>

<template>
    <div class="home-page">
        <header class="site-header">
            <div class="page-width header-inner">
                <a
                    class="brand"
                    href="#trang-chu"
                    aria-label="Hành Trang Đức Tin"
                >
                    <span class="brand-mark"
                        ><img
                            :src="'/images/02_individual_assets/logo-icon.png'"
                            alt=""
                    /></span>
                    <span
                        ><strong>Hành Trang Đức Tin</strong
                        ><small>Giáo xứ Cái Răng</small></span
                    >
                </a>
                <nav class="desktop-nav" aria-label="Điều hướng chính">
                    <a v-for="[href, label] in nav" :key="href" :href="href">{{
                        label
                    }}</a>
                </nav>
                <RouterLink class="header-login" :to="systemDestination"
                    ><LogIn :size="15" />{{ systemLabel }}</RouterLink
                >
                <button
                    class="menu-button"
                    aria-label="Mở menu"
                    @click="toggle(true)"
                >
                    <Menu />
                </button>
            </div>
        </header>

        <div class="mobile-drawer" :class="{ visible: open }">
            <button
                class="drawer-shade"
                aria-label="Đóng menu"
                @click="toggle(false)"
            />
            <aside>
                <div class="drawer-top">
                    <span class="brand"
                        ><span class="brand-mark"
                            ><img
                                :src="'/images/02_individual_assets/logo-icon.png'"
                                alt="" /></span
                        ><span
                            ><strong>Hành Trang Đức Tin</strong
                            ><small>Giáo xứ Cái Răng</small></span
                        ></span
                    ><button @click="toggle(false)"><X /></button>
                </div>
                <nav>
                    <a
                        v-for="[href, label] in nav"
                        :key="href"
                        :href="href"
                        @click="toggle(false)"
                        >{{ label }}<ChevronRight :size="16"
                    /></a>
                </nav>
                <RouterLink
                    :to="systemDestination"
                    class="primary-button"
                    @click="toggle(false)"
                    ><LogIn :size="16" />{{ systemLabel }}</RouterLink
                >
            </aside>
        </div>

        <main>
            <section id="trang-chu" class="hero">
                <div class="hero-art" />
                <img
                    class="hero-church"
                    :src="'/images/02_individual_assets/church-hero.png'"
                    alt="Nhà thờ Giáo xứ Cái Răng"
                />
                <img
                    class="hero-presenter"
                    :src="'/images/02_individual_assets/hero-catechism-child-real.png'"
                    alt="Thiếu nhi giới thiệu Hành Trang Đức Tin"
                />
                <div class="page-width hero-grid">
                    <div class="hero-copy">
                        <h1>Hành Trang Đức Tin</h1>
                        <h2>
                            Đồng hành trong hành trình<br />lớn lên trong đức
                            tin
                        </h2>
                        <p>
                            Hệ thống quản lý học giáo lý giúp Giáo xứ Cái Răng
                            quản lý lớp học, theo dõi tiến độ và kết nối giáo lý
                            viên, phụ huynh và thiếu nhi một cách dễ dàng, hiệu
                            quả và yêu thương.
                        </p>
                        <div class="hero-actions">
                            <RouterLink
                                :to="systemDestination"
                                class="primary-button"
                                >{{
                                    auth.isAuthenticated
                                        ? "Vào hệ thống"
                                        : "Đăng nhập ngay"
                                }}<ArrowRight :size="17"
                            /></RouterLink>
                            <a href="#chuc-nang" class="secondary-button"
                                >Tìm hiểu thêm<Play :size="15"
                            /></a>
                        </div>
                    </div>

                    <div
                        class="dashboard-preview"
                        aria-label="Minh họa giao diện quản lý"
                    >
                        <img
                            :src="'/images/02_individual_assets/dashboard-hero-mockup.png'"
                            alt="Giao diện tổng quan hệ thống giáo lý"
                        />
                    </div>
                </div>
            </section>

            <section id="gioi-thieu" class="stats-wrap">
                <div class="page-width stats-grid">
                    <article v-for="s in stats" :key="s.label">
                        <span class="round-icon"
                            ><component :is="s.icon" :size="27"
                        /></span>
                        <span
                            ><b>{{ s.value }}</b
                            ><strong>{{ s.label }}</strong
                            ><small>{{ s.note }}</small></span
                        >
                    </article>
                </div>
            </section>

            <section id="chuc-nang" class="section">
                <div class="page-width">
                    <h2 class="section-title">Chức năng nổi bật</h2>
                    <div class="feature-grid">
                        <article
                            v-for="f in features"
                            :key="f.title"
                            class="feature-card"
                        >
                            <span
                                class="feature-icon"
                                :style="{ background: f.tone, color: f.color }"
                                ><component :is="f.icon" :size="30"
                            /></span>
                            <h3>{{ f.title }}</h3>
                            <p>{{ f.text }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="doi-tuong" class="section audience-section">
                <div class="page-width">
                    <h2 class="section-title no-line">Dành cho ai?</h2>
                    <div class="audience-grid">
                        <article v-for="item in audiences" :key="item.title">
                            <img
                                class="avatar"
                                :src="item.avatar"
                                :alt="item.title"
                            />
                            <div>
                                <h3>{{ item.title }}</h3>
                                <ul>
                                    <li v-for="line in item.items" :key="line">
                                        {{ line }}
                                    </li>
                                </ul>
                            </div>
                        </article>
                    </div>

                    <h2 class="section-title no-line process-heading">
                        Quy trình sử dụng đơn giản
                    </h2>
                    <div class="process-card">
                        <template v-for="(step, index) in steps" :key="step.no">
                            <article>
                                <span
                                    class="step-number"
                                    :style="{ background: step.color }"
                                    >{{ step.no }}</span
                                >
                                <span class="step-icon"
                                    ><component :is="step.icon" :size="25"
                                /></span>
                                <span
                                    ><b>{{ step.title }}</b
                                    ><small>{{ step.text }}</small></span
                                >
                            </article>
                            <ArrowRight
                                v-if="index < steps.length - 1"
                                class="step-arrow"
                                :size="18"
                            />
                        </template>
                    </div>
                </div>
            </section>

            <section id="tin-tuc" class="section news-section">
                <div class="page-width news-grid">
                    <div class="news-box">
                        <div class="box-heading">
                            <h2>Lịch sinh hoạt sắp tới</h2>
                            <RouterLink to="/events">Xem tất cả</RouterLink>
                        </div>
                        <article v-for="event in events" :key="event.day">
                            <time
                                ><b>{{ event.day }}</b
                                ><small>{{ event.month }}</small></time
                            >
                            <div>
                                <h3>{{ event.title }}</h3>
                                <p>
                                    <Clock3 :size="13" />{{ event.time }}
                                    <span
                                        ><MapPin :size="13" />{{
                                            event.place
                                        }}</span
                                    >
                                </p>
                            </div>
                        </article>
                    </div>
                    <div class="news-box">
                        <div class="box-heading">
                            <h2>Thông báo mới</h2>
                            <RouterLink to="/news">Xem tất cả</RouterLink>
                        </div>
                        <article v-for="notice in notices" :key="notice.title">
                            <span
                                class="notice-icon"
                                :style="{
                                    background: notice.tone,
                                    color: notice.color,
                                }"
                                ><component :is="notice.icon" :size="21"
                            /></span>
                            <div>
                                <h3>
                                    {{ notice.title }}
                                    <em v-if="notice.badge">{{
                                        notice.badge
                                    }}</em>
                                </h3>
                                <p>{{ notice.text }}</p>
                                <small>2 giờ trước</small>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section class="cta">
                <div class="page-width cta-inner">
                    <img
                        class="cta-family"
                        :src="'/images/02_individual_assets/cta-jesus-with-children.png'"
                        alt="Chúa Giêsu đồng hành cùng thiếu nhi"
                    />
                    <div>
                        <h2>
                            Cùng nhau xây dựng một cộng đoàn<br />đức tin vững
                            mạnh
                        </h2>
                        <p>
                            Hành Trang Đức Tin - Người bạn đồng hành tin cậy của
                            Giáo xứ Cái Răng
                        </p>
                        <RouterLink :to="systemDestination" class="cta-button"
                            >{{
                                auth.isAuthenticated
                                    ? "Vào hệ thống"
                                    : "Đăng nhập ngay"
                            }}<ArrowRight :size="17"
                        /></RouterLink>
                    </div>
                    <img
                        class="cta-church"
                        :src="'/images/02_individual_assets/church-hero.png'"
                        alt=""
                    />
                </div>
            </section>
        </main>

        <footer id="lien-he">
            <div class="page-width footer-grid">
                <div>
                    <span class="footer-brand"
                        ><img
                            :src="'/images/02_individual_assets/logo-icon.png'"
                            alt=""
                        /><span
                            ><b>Hành Trang Đức Tin</b
                            ><small>Giáo xứ Cái Răng</small></span
                        ></span
                    >
                    <p>
                        Đồng hành cùng giáo lý viên, phụ huynh và thiếu nhi trên
                        hành trình lớn lên trong đức tin và nhân ái.
                    </p>
                    <div class="socials">
                        <a
                            href="mailto:giaoxucairang@gmail.com"
                            aria-label="Email"
                            ><Mail /></a
                        ><a href="tel:02812345678" aria-label="Điện thoại"
                            ><Phone
                        /></a>
                    </div>
                </div>
                <div>
                    <h3>Thông tin liên hệ</h3>
                    <p>
                        <MapPin :size="17" />Giáo xứ Cái Răng<br />Số 123, Đường
                        Cái Răng,<br />P. An Lạc, TP. Hồ Chí Minh
                    </p>
                    <p><Phone :size="17" />(028) 1234 5678</p>
                    <p><Mail :size="17" />giaoxucairang@gmail.com</p>
                    <p><LocateFixed :size="17" />www.giaoxucairang.org</p>
                </div>
                <div>
                    <h3>Liên kết nhanh</h3>
                    <nav>
                        <a href="#trang-chu">Trang chủ</a
                        ><a href="#gioi-thieu">Giới thiệu</a
                        ><a href="#chuc-nang">Chức năng</a
                        ><RouterLink to="/news">Tin tức</RouterLink
                        ><a href="#lien-he">Liên hệ</a>
                    </nav>
                </div>
                <div class="footer-support">
                    <h3>Hỗ trợ</h3>
                    <nav>
                        <span title="Đang phát triển">Câu hỏi thường gặp</span
                        ><span title="Đang phát triển">Hướng dẫn sử dụng</span
                        ><span title="Đang phát triển">Chính sách bảo mật</span
                        ><span title="Đang phát triển">Điều khoản sử dụng</span>
                    </nav>
                </div>
            </div>
            <p class="copyright">
                © {{ new Date().getFullYear() }} Giáo xứ Cái Răng. All rights
                reserved.
            </p>
        </footer>
    </div>
</template>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap");

.home-page {
    --blue: #064aa4;
    --primary: #0767d2;
    --ink: #063b83;
    --muted: #52657e;
    --line: #dbe8f7;
    background: #fff;
    color: #12345c;
    font-family: "Nunito", sans-serif;
}
.page-width {
    width: min(1120px, calc(100% - 32px));
    margin-inline: auto;
}
.site-header {
    height: 58px;
    background: #fff;
    border-bottom: 1px solid #dceafa;
    position: relative;
    z-index: 50;
}
.header-inner {
    height: 100%;
    display: flex;
    align-items: center;
}
.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--ink);
}
.brand-mark {
    display: grid;
    place-items: center;
    width: 39px;
    height: 39px;
    border-radius: 50%;
    color: #0754ad;
    background: #eaf4ff;
}
.brand strong,
.brand small {
    display: block;
}
.brand strong {
    font-size: 15px;
    line-height: 1.1;
    font-weight: 900;
}
.brand small {
    font-size: 10px;
    font-weight: 700;
}
.desktop-nav {
    margin-left: auto;
    display: flex;
    height: 100%;
    gap: 32px;
}
.desktop-nav a {
    display: grid;
    place-items: center;
    border-bottom: 2px solid transparent;
    color: #073f87;
    font-size: 12px;
    font-weight: 700;
}
.desktop-nav a:first-child {
    border-color: #1476df;
}
.header-login {
    margin-left: 34px;
    display: flex;
    align-items: center;
    gap: 6px;
    border-radius: 7px;
    background: #0767d2;
    padding: 8px 18px;
    color: #fff;
    font-size: 12px;
    font-weight: 800;
}
.menu-button {
    display: none;
    margin-left: auto;
    color: #0752a9;
}
.mobile-drawer {
    position: fixed;
    inset: 0;
    z-index: 100;
    pointer-events: none;
    opacity: 0;
    transition: 0.2s;
}
.mobile-drawer.visible {
    pointer-events: auto;
    opacity: 1;
}
.drawer-shade {
    position: absolute;
    inset: 0;
    background: #002e65aa;
}
.mobile-drawer aside {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: min(320px, 88vw);
    background: #fff;
    padding: 20px;
    transform: translateX(100%);
    transition: 0.25s;
}
.mobile-drawer.visible aside {
    transform: none;
}
.drawer-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--line);
    padding-bottom: 18px;
}
.mobile-drawer nav {
    display: flex;
    flex-direction: column;
    padding-block: 18px;
}
.mobile-drawer nav a {
    display: flex;
    justify-content: space-between;
    padding: 13px 8px;
    border-bottom: 1px solid #eef4fa;
    color: var(--ink);
    font-weight: 700;
}
.hero {
    height: 286px;
    position: relative;
    overflow: hidden;
    background: #eff9ff;
}
.hero-art {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(
            90deg,
            transparent 0 19%,
            rgba(246, 252, 255, 0.9) 35%,
            rgba(248, 253, 255, 0.5) 60%,
            transparent 100%
        ),
        url("/images/home-hero-parish.png");
    background-position: center 68%;
    background-size: cover;
}
.hero-grid {
    position: relative;
    height: 100%;
    display: grid;
    grid-template-columns: 1fr 1.15fr;
    gap: 40px;
    padding-top: 26px;
}
.hero-copy {
    margin-left: 20%;
    position: relative;
    z-index: 2;
}
.hero-copy h1 {
    color: #063e8d;
    font-size: 31px;
    font-weight: 900;
    line-height: 1.05;
}
.hero-copy h2 {
    margin-top: 7px;
    color: #07479a;
    font-size: 22px;
    font-weight: 900;
    line-height: 1.25;
}
.hero-copy p {
    margin-top: 12px;
    max-width: 390px;
    font-size: 11.5px;
    line-height: 1.65;
    color: #425875;
}
.hero-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}
.primary-button,
.secondary-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    border-radius: 9px;
    padding: 11px 18px;
    font-size: 12px;
    font-weight: 800;
}
.primary-button {
    background: #0767d2;
    color: #fff;
}
.secondary-button {
    border: 1.5px solid #1672dd;
    background: #fff;
    color: #0750ad;
}
.dashboard-preview {
    position: relative;
    height: 252px;
    border: 4px solid #4186d8;
    border-bottom: 0;
    border-radius: 140px 140px 0 0;
    padding: 29px 12px 0 47px;
    background: #ffffffaa;
    overflow: hidden;
}
.preview-shell {
    height: 235px;
    display: flex;
    border-radius: 8px 8px 0 0;
    background: #fff;
    box-shadow: 0 6px 24px #0b55961c;
    overflow: hidden;
}
.preview-shell > aside {
    width: 42px;
    display: flex;
    align-items: center;
    flex-direction: column;
    gap: 13px;
    background: #0754a5;
    padding-top: 13px;
}
.preview-shell > aside i {
    width: 17px;
    height: 17px;
    border-radius: 5px;
    background: #ffffff22;
}
.preview-shell > aside i.selected {
    background: #fff;
}
.mini-logo {
    color: #fff;
}
.preview-main {
    flex: 1;
    padding: 16px;
}
.preview-title {
    display: flex;
    justify-content: space-between;
    font-size: 9px;
}
.preview-title small {
    font-size: 7px;
}
.preview-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 7px;
    margin-top: 12px;
}
.preview-stats div {
    padding: 9px;
    border: 1px solid #e4edf8;
    border-radius: 5px;
}
.preview-stats small,
.preview-stats b {
    display: block;
}
.preview-stats small {
    font-size: 7px;
}
.preview-stats b {
    font-size: 13px;
    color: #0757b8;
}
.preview-panels {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 12px;
}
.preview-panels section {
    border: 1px solid #e3edf8;
    border-radius: 6px;
    padding: 10px;
    font-size: 7px;
}
.preview-panels section > b {
    font-size: 9px;
}
.preview-panels p {
    margin-top: 9px;
    color: #60748b;
}
.preview-panels strong,
.preview-panels > section > small {
    display: block;
}
.progress {
    margin-top: 8px;
}
.progress span {
    display: flex;
    justify-content: space-between;
}
.progress i {
    display: block;
    height: 3px;
    margin-top: 4px;
    background: #e4edf8;
}
.progress em {
    display: block;
    height: 100%;
    background: #0873df;
}
.stats-wrap {
    position: relative;
    margin-top: 18px;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    max-width: 765px;
    border: 1px solid var(--line);
    border-radius: 8px;
    box-shadow: 0 4px 16px #18589112;
    background: #fff;
}
.stats-grid article {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 18px;
    position: relative;
}
.stats-grid article + article:before {
    content: "";
    position: absolute;
    left: 0;
    height: 52px;
    border-left: 1px solid #d6e5f5;
}
.round-icon {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    flex: none;
    border-radius: 50%;
    background: #edf5ff;
    color: #176fd5;
}
.stats-grid b,
.stats-grid strong,
.stats-grid small {
    display: block;
}
.stats-grid b {
    color: #063f8e;
    font-size: 21px;
    font-weight: 900;
    line-height: 1;
}
.stats-grid strong {
    margin-top: 4px;
    color: #173d6c;
    font-size: 10px;
}
.stats-grid small {
    margin-top: 3px;
    font-size: 8px;
    color: #68798d;
}
.section {
    padding-top: 17px;
}
.section-title {
    text-align: center;
    color: #0b4a9d;
    font-size: 16px;
    font-weight: 900;
    margin-bottom: 15px;
}
.section-title:after {
    content: "";
    display: block;
    width: 24px;
    border-bottom: 2px solid #ffaf14;
    margin: 7px auto 0;
}
.section-title.no-line:after {
    display: none;
}
.feature-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 12px;
}
.feature-card {
    min-height: 104px;
    border: 1px solid var(--line);
    border-radius: 7px;
    text-align: center;
    padding: 12px 10px;
    box-shadow: 0 3px 12px #194e8110;
}
.feature-icon {
    display: grid;
    place-items: center;
    width: 45px;
    height: 45px;
    margin: 0 auto 7px;
    border-radius: 50%;
}
.feature-card h3 {
    font-size: 11px;
    font-weight: 900;
    color: #084b9c;
}
.feature-card p {
    font-size: 8px;
    line-height: 1.55;
    color: #435a75;
}
.audience-section {
    padding-top: 15px;
}
.audience-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    max-width: 740px;
}
.audience-grid article {
    height: 84px;
    display: flex;
    overflow: hidden;
    border: 1px solid var(--line);
    border-radius: 7px;
    background: #f2f8ff;
    padding: 8px 7px;
}
.avatar {
    align-self: flex-end;
    font-size: 46px;
    line-height: 0.9;
    filter: drop-shadow(0 2px 1px #a7b8c8);
}
.audience-grid h3 {
    font-size: 10px;
    color: #0752ac;
    font-weight: 900;
}
.audience-grid ul {
    margin-top: 8px;
    display: grid;
    gap: 5px;
    font-size: 6.5px;
}
.audience-grid li:before {
    content: "•";
    color: #5b993e;
    margin-right: 4px;
}
.process-heading {
    text-align: left;
    margin: 15px 0 8px 160px;
}
.process-card {
    max-width: 740px;
    display: flex;
    align-items: center;
    border: 1px solid var(--line);
    border-radius: 7px;
    padding: 10px 14px;
}
.process-card article {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
}
.step-number {
    display: grid;
    place-items: center;
    width: 26px;
    height: 26px;
    flex: none;
    border-radius: 50%;
    color: #fff;
    font-weight: 900;
}
.step-icon {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    flex: none;
    border-radius: 7px;
    background: #eef6ff;
    color: #1570d2;
}
.process-card b,
.process-card small {
    display: block;
}
.process-card b {
    font-size: 8px;
    color: #0750a5;
}
.process-card small {
    font-size: 6px;
    line-height: 1.45;
    margin-top: 4px;
}
.step-arrow {
    color: #6a9ed2;
    flex: none;
}
.news-section {
    padding-top: 12px;
}
.news-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    max-width: 740px;
    margin-left: calc((100% - min(1120px, calc(100% - 32px))) / 2 + 380px);
}
.news-box {
    border: 1px solid var(--line);
    border-radius: 7px;
    padding: 10px 12px;
}
.box-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--line);
    padding-bottom: 7px;
}
.box-heading h2 {
    font-size: 10px;
    color: #0750a5;
    font-weight: 900;
}
.box-heading a {
    font-size: 7px;
    color: #075ed0;
    text-decoration: underline;
}
.news-box article {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 0;
}
.news-box article + article {
    border-top: 1px solid #e5eef8;
}
.news-box time {
    display: grid;
    place-items: center;
    width: 40px;
    height: 43px;
    flex: none;
    border-radius: 6px;
    background: #eff6ff;
    color: #0753af;
}
.news-box time b {
    font-size: 18px;
    line-height: 1;
}
.news-box time small {
    font-size: 6px;
    font-weight: 800;
}
.news-box h3 {
    font-size: 8px;
    color: #0750a5;
    font-weight: 900;
}
.news-box p {
    display: flex;
    gap: 12px;
    margin-top: 5px;
    font-size: 6px;
    color: #4f6882;
}
.news-box p span,
.news-box p {
    align-items: center;
}
.news-box p span {
    display: flex;
    gap: 3px;
}
.notice-icon {
    display: grid;
    place-items: center;
    width: 37px;
    height: 37px;
    flex: none;
    border-radius: 6px;
}
.news-box h3 em {
    background: #f04452;
    color: #fff;
    border-radius: 4px;
    padding: 1px 3px;
    font-size: 5px;
}
.news-box article > div > small {
    font-size: 6px;
    color: #8290a0;
}
.cta {
    margin-top: 22px;
}
.cta-inner {
    height: 80px;
    border-radius: 28px 28px 0 0;
    background: linear-gradient(100deg, #197bdc, #3a8de8);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 45px;
    overflow: hidden;
    text-align: center;
}
.cta-family {
    font-size: 43px;
    white-space: nowrap;
}
.cta-family span {
    font-size: 28px;
    margin-left: -12px;
}
.cta h2 {
    font-size: 17px;
    font-weight: 900;
}
.cta p {
    font-size: 9px;
    margin-top: 2px;
}
.cta-button {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #ffc51f;
    color: #153b67;
    border-radius: 6px;
    padding: 6px 24px;
    margin-top: 8px;
    font-size: 8px;
    font-weight: 900;
}
.cta-church {
    color: #ddecff;
    align-self: flex-end;
}
footer {
    background: linear-gradient(100deg, #02386e, #002b59);
    color: #fff;
}
.footer-grid {
    display: grid;
    grid-template-columns: 1.3fr 1.2fr 0.8fr 0.8fr;
    gap: 42px;
    padding-top: 16px;
}
.footer-grid > div + div {
    border-left: 1px solid #ffffff35;
    padding-left: 28px;
}
.footer-brand {
    display: flex;
    align-items: center;
    gap: 9px;
}
.footer-brand b,
.footer-brand small {
    display: block;
}
.footer-brand b {
    font-size: 11px;
}
.footer-brand small {
    font-size: 7px;
}
.footer-grid p {
    display: flex;
    gap: 8px;
    font-size: 7px;
    line-height: 1.6;
    margin-top: 9px;
    color: #e3f0ff;
}
.footer-grid h3 {
    font-size: 9px;
    margin-bottom: 8px;
}
.socials {
    display: flex;
    gap: 8px;
    margin-top: 10px;
}
.socials a {
    display: grid;
    place-items: center;
    width: 23px;
    height: 23px;
    border-radius: 50%;
    background: #145895;
}
.socials svg {
    width: 12px;
}
.footer-grid nav {
    display: grid;
    gap: 5px;
    font-size: 7px;
    color: #e3f0ff;
}
.copyright {
    text-align: center !important;
    font-size: 6px !important;
    margin: 6px 0 0 !important;
    padding-bottom: 5px;
}
@media (max-width: 900px) {
    .desktop-nav,
    .header-login {
        display: none;
    }
    .menu-button {
        display: block;
    }
    .hero {
        height: 402px;
    }
    .hero-grid {
        grid-template-columns: 1fr;
    }
    .hero-art {
        background-image:
            linear-gradient(
                90deg,
                rgba(247, 252, 255, 0.97) 0 44%,
                rgba(247, 252, 255, 0.2) 76%
            ),
            url("/images/home-hero-parish.png");
        background-position: 25% bottom;
    }
    .hero-copy {
        margin-left: 0;
        width: 50%;
    }
    .dashboard-preview {
        display: none;
    }
    .feature-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .news-grid {
        margin-inline: auto;
    }
    .footer-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .footer-support {
        display: none;
    }
}
@media (max-width: 640px) {
    .page-width {
        width: min(100% - 28px, 680px);
    }
    .site-header {
        height: 74px;
    }
    .brand-mark {
        width: 47px;
        height: 47px;
    }
    .brand strong {
        font-size: 17px;
    }
    .brand small {
        font-size: 11px;
    }
    .hero {
        height: 410px;
    }
    .hero-art {
        background-position: 26% 95%;
        background-size: auto 275px;
        background-repeat: no-repeat;
        background-color: #eef9ff;
    }
    .hero-grid {
        padding-top: 42px;
    }
    .hero-copy {
        width: 100%;
    }
    .hero-copy h1 {
        font-size: 30px;
    }
    .hero-copy h2 {
        font-size: 21px;
        width: 58%;
    }
    .hero-copy p {
        font-size: 13px;
        width: 47%;
        line-height: 1.65;
    }
    .hero-actions {
        margin-top: 27px;
    }
    .primary-button,
    .secondary-button {
        padding: 12px 18px;
    }
    .stats-wrap {
        margin-top: 14px;
    }
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
        width: calc(100% - 36px);
    }
    .stats-grid article {
        padding: 14px 8px;
        gap: 6px;
        justify-content: center;
    }
    .round-icon {
        width: 35px;
        height: 35px;
    }
    .round-icon svg {
        width: 23px;
    }
    .stats-grid b {
        font-size: 20px;
    }
    .stats-grid strong {
        font-size: 9px;
    }
    .stats-grid small {
        font-size: 7px;
    }
    .section {
        padding-top: 20px;
    }
    .section-title {
        font-size: 18px;
    }
    .feature-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
    }
    .feature-card {
        min-height: 155px;
        padding: 14px 8px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    .feature-icon {
        width: 52px;
        height: 52px;
    }
    .feature-card h3 {
        font-size: 13px;
    }
    .feature-card p {
        font-size: 10px;
    }
    .audience-grid {
        gap: 7px;
    }
    .audience-grid article {
        height: 132px;
        display: block;
        text-align: center;
        padding: 7px 4px;
    }
    .avatar {
        display: block;
        font-size: 44px;
    }
    .audience-grid h3 {
        font-size: 10px;
    }
    .audience-grid ul {
        font-size: 7px;
        text-align: left;
        margin-left: 8px;
    }
    .process-heading {
        text-align: center;
        margin: 23px 0 10px;
    }
    .process-card {
        padding: 13px 10px;
    }
    .process-card article {
        gap: 5px;
    }
    .step-number {
        width: 24px;
        height: 24px;
    }
    .step-icon {
        width: 34px;
        height: 34px;
    }
    .process-card b {
        font-size: 8px;
    }
    .process-card small {
        font-size: 6px;
    }
    .news-grid {
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }
    .news-box {
        padding: 12px;
    }
    .box-heading h2 {
        font-size: 11px;
    }
    .news-box article {
        min-height: 67px;
    }
    .news-box h3 {
        font-size: 8px;
    }
    .news-box p {
        font-size: 6.5px;
        line-height: 1.45;
        display: block;
    }
    .cta-inner {
        height: 173px;
        border-radius: 26px 26px 0 0;
        gap: 5px;
        padding: 15px;
    }
    .cta-family {
        font-size: 33px;
        align-self: flex-end;
    }
    .cta-family span {
        font-size: 21px;
    }
    .cta h2 {
        font-size: 16px;
    }
    .cta p {
        font-size: 9px;
    }
    .cta-church {
        width: 80px;
    }
    .footer-grid {
        grid-template-columns: 1fr 1.15fr 0.8fr;
        gap: 18px;
        padding: 28px 20px 5px;
        width: 100%;
    }
    .footer-grid > div + div {
        padding-left: 18px;
    }
    .footer-brand b {
        font-size: 15px;
    }
    .footer-brand small {
        font-size: 10px;
    }
    .footer-grid p,
    .footer-grid nav {
        font-size: 9px;
    }
    .footer-grid h3 {
        font-size: 11px;
    }
    .copyright {
        font-size: 9px !important;
        padding: 12px !important;
    }
}
@media (max-width: 430px) {
    .hero-copy h2 {
        width: 70%;
    }
    .hero-copy p {
        width: 58%;
        font-size: 12px;
    }
    .hero-art {
        background-position: 19% bottom;
    }
    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        width: calc(100% - 28px);
    }
    .stats-grid article {
        justify-content: flex-start;
        padding: 12px;
    }
    .stats-grid article + article:before {
        display: none;
    }
    .feature-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }
    .feature-card {
        min-height: 148px;
    }
    .audience-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .process-card {
        display: grid;
        grid-template-columns: 1fr;
    }
    .step-arrow {
        display: none;
    }
    .process-card article {
        padding: 8px;
    }
    .news-grid {
        grid-template-columns: 1fr;
    }
    .cta-family,
    .cta-church {
        display: none;
    }
    .footer-grid {
        grid-template-columns: 1fr;
    }
    .footer-grid > div + div {
        border-left: 0;
        padding-left: 0;
    }
    .footer-grid > div:nth-child(3) {
        display: none;
    }
}

/* Desktop proportions from the supplied home-pc reference. */
@media (min-width: 901px) {
    .page-width {
        width: min(1400px, 88%);
    }
    .desktop-nav {
        gap: 38px;
    }
    .header-login {
        margin-left: 38px;
    }
    .hero {
        height: 410px;
    }
    .hero-grid {
        grid-template-columns: 0.9fr 1.1fr;
        gap: 54px;
        align-items: center;
        padding-top: 0;
    }
    .hero-copy {
        margin-left: 14%;
    }
    .hero-copy h1 {
        font-size: 32px;
    }
    .hero-copy h2 {
        font-size: 23px;
    }
    .hero-copy p {
        max-width: 410px;
        font-size: 12px;
    }
    .stats-grid {
        width: min(1050px, 76%);
        max-width: none;
    }
    .audience-grid,
    .process-card {
        width: 65%;
        max-width: none;
    }
    .process-heading {
        width: 65%;
        margin: 15px 0 8px;
        text-align: center;
    }
    .news-section {
        margin-top: -205px;
        padding-top: 0;
        pointer-events: none;
    }
    .news-grid {
        width: 32%;
        max-width: none;
        margin-right: 6%;
        margin-left: auto;
        pointer-events: auto;
    }
    .cta {
        margin-top: 32px;
        margin-bottom: 32px;
    }
    .cta-inner {
        height: 190px;
        gap: 90px;
        padding: 24px 55px;
        border-radius: 34px;
    }
}

/* Readable type scale */
.brand strong {
    font-size: 17px;
}
.brand small {
    font-size: 11px;
}
.desktop-nav a,
.header-login {
    font-size: 14px;
}
.section-title {
    font-size: 21px;
}
.feature-card h3 {
    font-size: 14px;
}
.feature-card p {
    font-size: 11px;
    line-height: 1.55;
}
.stats-grid strong {
    font-size: 13px;
}
.stats-grid small {
    font-size: 10px;
}
.audience-grid h3 {
    font-size: 13px;
}
.audience-grid ul {
    font-size: 10px;
    line-height: 1.35;
}
.process-card b {
    font-size: 12px;
}
.process-card small {
    font-size: 9px;
    line-height: 1.45;
}
.box-heading h2 {
    font-size: 14px;
}
.box-heading a {
    font-size: 10px;
}
.news-box h3 {
    font-size: 11px;
}
.news-box p {
    font-size: 9px;
    line-height: 1.45;
}
.news-box article > div > small {
    font-size: 9px;
}
.news-box h3 em {
    font-size: 8px;
}
.cta h2 {
    font-size: 20px;
}
.cta p {
    font-size: 12px;
}
.cta-button {
    font-size: 11px;
}
.footer-brand b {
    font-size: 14px;
}
.footer-brand small {
    font-size: 10px;
}
.footer-grid h3 {
    font-size: 13px;
}
.footer-grid p,
.footer-grid nav {
    font-size: 10px;
}
.copyright {
    font-size: 9px !important;
}

@media (min-width: 901px) {
    .hero-copy h1 {
        font-size: 38px;
    }
    .hero-copy h2 {
        font-size: 28px;
    }
    .hero-copy p {
        max-width: 470px;
        font-size: 14px;
    }
    .primary-button,
    .secondary-button {
        font-size: 14px;
    }
    .dashboard-preview {
        height: 352px;
        border-radius: 190px 190px 0 0;
        padding-top: 42px;
    }
    .preview-shell {
        height: 326px;
    }
    .preview-main {
        padding: 22px;
    }
    .preview-stats {
        margin-top: 17px;
    }
    .preview-panels {
        margin-top: 17px;
    }
    .stats-grid article {
        padding: 16px 22px;
    }
    .stats-grid b {
        font-size: 26px;
    }
    .feature-card {
        min-height: 145px;
        padding: 18px 14px;
    }
    .feature-icon {
        width: 54px;
        height: 54px;
    }
    .audience-grid article {
        height: 126px;
        padding: 12px 10px;
    }
    .avatar {
        font-size: 58px;
    }
    .audience-grid ul {
        gap: 7px;
        margin-top: 10px;
    }
    .process-heading {
        margin-top: 20px;
    }
    .process-card {
        min-height: 88px;
        padding: 14px 18px;
    }
    .step-number {
        width: 32px;
        height: 32px;
        font-size: 15px;
    }
    .step-icon {
        width: 46px;
        height: 46px;
    }
    .news-section {
        margin-top: -286px;
    }
    .news-box {
        padding: 14px 16px;
    }
    .box-heading {
        padding-bottom: 10px;
    }
    .news-box article {
        min-height: 75px;
        padding: 11px 0;
    }
    .news-box time {
        width: 48px;
        height: 52px;
    }
    .news-box time b {
        font-size: 21px;
    }
    .news-box time small {
        font-size: 8px;
    }
    .notice-icon {
        width: 44px;
        height: 44px;
    }
    .cta-inner {
        height: 190px;
    }
    .cta-family {
        font-size: 68px;
    }
    .cta-family span {
        font-size: 42px;
    }
    .cta h2 {
        font-size: 27px;
        line-height: 1.3;
    }
    .cta p {
        margin-top: 8px;
        font-size: 14px;
    }
    .cta-button {
        margin-top: 16px;
        padding: 11px 34px;
        font-size: 14px;
        border-radius: 9px;
    }
    .cta-church {
        width: 150px;
        height: 150px;
    }
    .footer-grid {
        padding-top: 24px;
        padding-bottom: 12px;
    }
    .footer-grid p {
        line-height: 1.65;
    }
    .footer-grid nav {
        gap: 8px;
    }
    .copyright {
        padding: 8px 0 10px !important;
    }
}

@media (min-width: 901px) {
    .hero {
        background: linear-gradient(
            90deg,
            #eef9ff 0%,
            #f8fdff 48%,
            #edf9ff 100%
        );
    }
    .hero-art {
        background-color: transparent;
        background-image:
            linear-gradient(
                90deg,
                transparent 0 32%,
                rgba(247, 252, 255, 0.88) 43%,
                rgba(247, 252, 255, 0.98) 58%,
                transparent 78%
            ),
            url("/images/home-hero-parish.png");
        background-position: left center;
        background-repeat: no-repeat;
        background-size: 900px auto;
    }
    .hero-copy {
        margin-left: 11%;
        padding-left: 125px;
    }
    .dashboard-preview {
        align-self: end;
        width: 100%;
        height: 380px;
        padding: 44px 10px 0 48px;
    }
    .preview-shell {
        height: 336px;
    }
    .preview-main {
        display: flex;
        min-width: 0;
        flex-direction: column;
    }
    .preview-panels {
        flex: 1;
        grid-template-columns: 1fr 1fr;
        margin-top: 17px;
    }
    .preview-panels section {
        min-height: 190px;
        padding: 15px;
    }
    .preview-panels section > b {
        font-size: 11px;
    }
    .preview-panels p {
        margin-top: 16px;
    }
    .progress {
        margin-top: 16px;
    }
}

@media (max-width: 900px) {
    .news-section {
        margin-top: 0;
    }
    .audience-grid,
    .process-card {
        width: 100%;
        max-width: none;
    }
}

@media (max-width: 640px) {
    .feature-card h3 {
        font-size: 14px;
    }
    .feature-card p {
        font-size: 11px;
    }
    .audience-grid h3 {
        font-size: 12px;
    }
    .audience-grid ul {
        font-size: 9px;
    }
    .process-card b {
        font-size: 11px;
    }
    .process-card small {
        font-size: 9px;
    }
    .box-heading h2 {
        font-size: 14px;
    }
    .box-heading a {
        font-size: 10px;
    }
    .news-box h3 {
        font-size: 11px;
    }
    .news-box p {
        font-size: 9px;
    }
    .footer-grid h3 {
        font-size: 13px;
    }
    .footer-grid p,
    .footer-grid nav {
        font-size: 11px;
    }
}

/* Supplied production artwork */
.brand-mark {
    position: relative;
    overflow: hidden;
    background: transparent;
}
.brand-mark img {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 140px;
    height: auto;
    max-width: none;
    transform: translate(-50%, calc(-50% + 4px));
}
.footer-brand > img {
    width: 44px;
    height: 44px;
    object-fit: contain;
}
.hero-church,
.hero-children {
    position: absolute;
    z-index: 1;
    pointer-events: none;
    object-fit: contain;
}
.hero-presenter {
    position: absolute;
    z-index: 2;
    bottom: 0;
    left: clamp(-100px, calc((100vw - 1200px) * 0.25 - 100px), 100px);
    width: auto;
    height: min(390px, 100%);
    max-width: none;
    pointer-events: none;
    object-fit: contain;
    object-position: center bottom;
}
@media (min-width: 1200px) and (max-width: 1599px) {
    .hero-presenter {
        left: clamp(-80px, calc((100vw - 1440px) * 0.333), 40px);
        height: 330px;
    }
}
@media (min-width: 1600px) {
    .hero-grid {
        transform: translateX(
            clamp(0px, calc((100vw - 1600px) * 0.3125), 100px)
        );
    }
    .hero-presenter {
        left: calc((100vw - 1400px) / 2 - 60px);
    }
}
.dashboard-preview > img {
    display: block;
    max-width: none;
}
.avatar {
    width: 92px;
    height: 112px;
    flex: none;
    align-self: flex-end;
    object-fit: contain;
    filter: none;
}
.cta-family {
    width: 300px;
    height: 190px;
    flex: none;
    object-fit: contain;
}
.cta-church {
    width: 190px;
    height: 190px;
    flex: none;
    align-self: flex-end;
    object-fit: contain;
}

@media (min-width: 901px) {
    .hero-art {
        background-image:
            radial-gradient(
                circle at 24% 45%,
                rgba(255, 255, 255, 0.98),
                rgba(255, 255, 255, 0.72) 26%,
                transparent 48%
            ),
            linear-gradient(90deg, #eaf7ff 0%, #f9fdff 52%, #e7f7ff 100%);
        background-size: cover;
    }
    .hero-church {
        left: -125px;
        bottom: -125px;
        width: 720px;
        height: auto;
    }
    .hero-children {
        left: 55px;
        bottom: -235px;
        width: 650px;
        height: auto;
    }
    .hero-copy {
        z-index: 3;
        margin-left: 10%;
        padding-left: 155px;
    }
    .dashboard-preview {
        position: relative;
        align-self: stretch;
        height: 410px;
        overflow: hidden;
        border: 0;
        border-radius: 0;
        background: transparent;
        padding: 0;
    }
    .dashboard-preview > img {
        position: absolute;
        top: -25%;
        left: -35%;
        width: 165%;
        height: auto;
    }
}

@media (max-width: 900px) {
    .hero-art {
        background-image: linear-gradient(135deg, #f4fbff, #e8f7ff);
    }
    .hero-church {
        right: -130px;
        bottom: -110px;
        width: 560px;
    }
    .hero-children {
        right: -70px;
        bottom: -105px;
        width: 500px;
    }
    .hero-presenter {
        display: none;
        right: -20px;
        left: auto;
        height: 300px;
    }
}

@media (max-width: 640px) {
    .hero-church {
        right: -170px;
        bottom: -95px;
        width: 500px;
    }
    .hero-children {
        right: -105px;
        bottom: -90px;
        width: 430px;
    }
    .hero-presenter {
        right: -45px;
        height: 260px;
    }
    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        width: calc(100% - 28px);
    }
    .stats-grid article {
        justify-content: flex-start;
        padding: 12px;
    }
    .stats-grid article + article:before {
        display: none;
    }
    .feature-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }
    .avatar {
        width: 100%;
        height: 72px;
    }
    .cta-family {
        width: 145px;
        height: 160px;
    }
    .cta-church {
        width: 100px;
        height: 160px;
    }
}

/* Responsive layout corrections */
.home-page {
    width: 100%;
    min-width: 0;
    overflow-x: clip;
}
.home-page *,
.home-page *::before,
.home-page *::after {
    box-sizing: border-box;
}
.hero-copy,
.dashboard-preview,
.audience-grid article > div,
.process-card article > span:last-child,
.news-box article > div {
    min-width: 0;
}
.hero-copy h1,
.hero-copy h2,
.audience-grid h3,
.process-card b,
.news-box h3 {
    overflow-wrap: anywhere;
}

/* Keep the asymmetric desktop composition only where it has enough room. */
@media (min-width: 1200px) {
    .hero-copy {
        margin-left: clamp(52px, 5vw, 72px);
        padding-left: clamp(20px, calc((100vw - 1100px) * 0.2), 80px);
    }
    .dashboard-preview > img {
        left: -28%;
        width: 155%;
    }
    .audience-grid,
    .process-card {
        width: 62%;
    }
    .process-heading {
        width: 62%;
    }
    .news-grid {
        width: 31%;
        margin-right: 6%;
    }
    .cta-inner {
        height: 230px;
        gap: 40px;
        padding: 20px 50px;
    }
    .cta-family {
        width: 250px;
        height: 205px;
    }
    .cta-inner > div {
        min-width: 0;
        flex: 1;
    }
    .cta h2 {
        font-size: 26px;
        line-height: 1.25;
    }
    .cta p {
        font-size: 13px;
        line-height: 1.45;
    }
    .cta-church {
        width: 150px;
        height: 195px;
    }
}

/* Compact desktop and landscape tablet. */
@media (min-width: 901px) and (max-width: 1199px) {
    .page-width {
        width: calc(100% - 40px);
    }
    .desktop-nav {
        gap: clamp(14px, 2vw, 24px);
    }
    .header-login {
        margin-left: 18px;
        padding-inline: 14px;
    }
    .hero {
        height: 380px;
    }
    .hero-grid {
        grid-template-columns: minmax(0, 47%) minmax(0, 53%);
        gap: 16px;
    }
    .hero-copy {
        width: auto;
        margin-left: clamp(38px, 5vw, 60px);
        padding-left: 0;
    }
    .hero-copy h1 {
        font-size: clamp(32px, 3.5vw, 38px);
    }
    .hero-copy h2 {
        font-size: clamp(23px, 2.6vw, 28px);
    }
    .hero-copy p {
        max-width: 390px;
        font-size: 13px;
    }
    .hero-church {
        left: -180px;
        bottom: -120px;
        width: 650px;
    }
    .hero-children {
        left: -35px;
        bottom: -215px;
        width: 590px;
    }
    .hero-presenter {
        display: none;
        left: -80px;
        height: 300px;
    }
    .dashboard-preview {
        height: 380px;
    }
    .dashboard-preview > img {
        top: -14%;
        left: -24%;
        width: 150%;
    }
    .stats-grid {
        width: 90%;
    }
    .feature-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .audience-grid,
    .process-card {
        width: 100%;
    }
    .process-heading {
        width: 100%;
    }
    .audience-grid article {
        height: auto;
        min-height: 132px;
    }
    .news-section {
        margin-top: 0;
        padding-top: 18px;
    }
    .news-grid {
        width: calc(100% - 40px);
        max-width: none;
        margin-inline: auto;
    }
    .cta-inner {
        height: 220px;
        gap: 28px;
        padding: 18px 32px;
    }
    .cta-family {
        width: 220px;
        height: 195px;
    }
    .cta-inner > div {
        min-width: 0;
        flex: 1;
    }
    .cta h2 {
        font-size: 23px;
        line-height: 1.25;
    }
    .cta p {
        font-size: 12px;
        line-height: 1.45;
    }
    .cta-church {
        width: 130px;
        height: 185px;
    }
}

/* Desktop: keep the lower content on one consistent, readable grid. */
@media (min-width: 1200px) {
    .audience-grid,
    .process-card {
        width: 100%;
    }
    .process-heading {
        width: 100%;
        margin-top: 24px;
        margin-bottom: 12px;
        font-size: 22px;
    }
    .audience-grid {
        gap: 14px;
    }
    .audience-grid article {
        min-height: 150px;
        height: auto;
        align-items: center;
        padding: 15px 16px;
    }
    .audience-grid .avatar {
        width: 105px;
        height: 128px;
    }
    .audience-grid h3 {
        font-size: 15px;
        line-height: 1.3;
    }
    .audience-grid ul {
        gap: 7px;
        margin-top: 10px;
        font-size: 11px;
        line-height: 1.4;
    }
    .process-card {
        min-height: 108px;
        padding: 17px 22px;
    }
    .process-card article {
        gap: 11px;
    }
    .step-number {
        width: 34px;
        height: 34px;
        font-size: 16px;
    }
    .step-icon {
        width: 50px;
        height: 50px;
    }
    .process-card b {
        font-size: 14px;
    }
    .process-card small {
        margin-top: 5px;
        font-size: 11px;
        line-height: 1.45;
    }
    .step-arrow {
        width: 22px;
        height: 22px;
    }
    .news-section {
        margin-top: 0;
        padding-top: 22px;
    }
    .news-grid {
        width: min(1400px, 88%);
        max-width: none;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
        margin-inline: auto;
    }
    .news-box {
        padding: 18px 20px;
    }
    .box-heading {
        padding-bottom: 12px;
    }
    .box-heading h2 {
        font-size: 18px;
    }
    .box-heading a {
        flex: none;
        font-size: 11px;
        white-space: nowrap;
    }
    .news-box article {
        min-height: 92px;
        align-items: center;
        gap: 15px;
        padding: 14px 0;
    }
    .news-box h3 {
        font-size: 14px;
        overflow-wrap: normal;
    }
    .news-box p {
        font-size: 11px;
        line-height: 1.5;
    }
    .news-box article > div > small {
        display: block;
        margin-top: 5px;
        font-size: 10px;
    }
    .news-box time {
        width: 58px;
        height: 62px;
    }
    .news-box time b {
        font-size: 24px;
    }
    .news-box time small {
        font-size: 8px;
    }
    .notice-icon {
        width: 52px;
        height: 52px;
    }
}

/* Tablet: cards need two useful columns instead of four cramped ones. */
@media (max-width: 900px) {
    .audience-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }
    .audience-grid article {
        height: auto;
        min-height: 150px;
        align-items: center;
        padding: 12px;
    }
    .audience-grid .avatar {
        width: 100px;
        height: 124px;
    }
    .audience-grid h3 {
        font-size: 14px;
    }
    .audience-grid ul {
        font-size: 10px;
    }
    .cta {
        margin-top: 28px;
    }
    .cta-inner {
        width: calc(100% - 32px);
        height: auto;
        min-height: 200px;
        gap: 20px;
        padding: 22px 28px;
    }
    .cta-family {
        width: 190px;
        height: 175px;
    }
    .cta-inner > div {
        min-width: 0;
        flex: 1;
    }
    .cta h2 {
        font-size: 22px;
        line-height: 1.3;
    }
    .cta p {
        margin-top: 7px;
        font-size: 12px;
        line-height: 1.45;
    }
    .cta-button {
        margin-top: 12px;
        padding: 10px 24px;
        font-size: 12px;
    }
    .cta-church {
        width: 110px;
        height: 165px;
    }
}

@media (max-width: 760px) {
    .audience-grid article {
        height: auto;
        min-height: 154px;
        display: flex;
        align-items: center;
        text-align: left;
        padding: 12px 10px;
    }
    .audience-grid .avatar {
        width: 92px;
        height: 118px;
        align-self: flex-end;
    }
    .audience-grid ul {
        margin-left: 0;
    }
    .process-card {
        align-items: stretch;
    }
    .process-card article {
        align-items: flex-start;
    }
    .footer-grid {
        grid-template-columns: 1.15fr 1fr;
    }
    .footer-grid > div:nth-child(3) {
        border-left: 0;
        padding-left: 0;
    }
}

@media (max-width: 640px) {
    .hero-copy h2 {
        max-width: 330px;
    }
    .stats-grid {
        gap: 0;
    }
    .stats-grid article {
        min-width: 0;
    }
    .feature-card {
        height: auto;
    }
    .process-card article {
        align-items: center;
    }
    .news-box {
        min-width: 0;
    }
    .cta-inner {
        min-height: 210px;
        gap: 10px;
        padding: 22px 18px;
    }
    .cta-family {
        width: 115px;
        height: 165px;
    }
    .cta-church {
        width: 72px;
        height: 150px;
    }
    .cta h2 {
        font-size: 19px;
    }
}

@media (max-width: 600px) {
    .process-card {
        display: grid;
        grid-template-columns: 1fr;
        padding: 8px 14px;
    }
    .process-card article {
        min-height: 66px;
        padding: 10px 4px;
    }
    .process-card article:not(:first-child) {
        border-top: 1px solid var(--line);
    }
    .process-card b {
        font-size: 12px;
    }
    .process-card small {
        font-size: 10px;
    }
    .step-arrow {
        display: none;
    }
    .news-grid {
        grid-template-columns: 1fr;
    }
    .news-box h3 {
        font-size: 12px;
    }
    .news-box p {
        font-size: 10px;
    }
}

@media (max-width: 540px) {
    .cta-inner {
        min-height: 190px;
        padding: 24px 18px;
    }
    .cta-family,
    .cta-church {
        display: none;
    }
    .cta-inner > div {
        width: 100%;
    }
    .cta h2 {
        font-size: 21px;
    }
    .cta p {
        font-size: 12px;
    }
}

@media (max-width: 430px) {
    .page-width {
        width: calc(100% - 28px);
    }
    .hero {
        height: 430px;
    }
    .hero-grid {
        padding-top: 34px;
    }
    .hero-copy h1 {
        font-size: 28px;
    }
    .hero-copy h2 {
        width: 72%;
        font-size: 20px;
    }
    .hero-copy p {
        width: 62%;
        font-size: 11.5px;
    }
    .hero-actions {
        width: 100%;
        gap: 10px;
        margin-top: 22px;
    }
    .hero-actions a {
        flex: 1;
        padding-inline: 10px;
        white-space: nowrap;
    }
    .hero-presenter {
        right: -55px;
        height: 230px;
    }
    .stats-grid {
        width: calc(100% - 28px);
    }
    .stats-grid article {
        padding: 12px 9px;
    }
    .audience-grid article {
        min-height: 178px;
        display: block;
        text-align: center;
        padding: 9px 8px 12px;
    }
    .audience-grid .avatar {
        width: 100%;
        height: 78px;
    }
    .audience-grid ul {
        margin-top: 7px;
        text-align: left;
    }
    .process-card article {
        padding: 10px 4px;
    }
    .cta-inner {
        height: auto;
        min-height: 190px;
        padding: 20px 14px;
    }
    .footer-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 350px) {
    .site-header {
        height: 66px;
    }
    .brand-mark {
        width: 42px;
        height: 42px;
    }
    .brand strong {
        font-size: 15px;
    }
    .brand small {
        font-size: 10px;
    }
    .hero-copy h2 {
        width: 76%;
    }
    .hero-copy p {
        width: 66%;
    }
    .hero-actions {
        gap: 7px;
    }
    .primary-button,
    .secondary-button {
        font-size: 11px;
    }
    .feature-card {
        min-height: 132px;
    }
    .audience-grid article {
        min-height: 160px;
    }
}
</style>
