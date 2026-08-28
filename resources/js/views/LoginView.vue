<script setup lang="ts">
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  Eye,
  EyeOff,
  LockKeyhole,
  Mail,
  ShieldCheck,
} from 'lucide-vue-next';
import { useAuthStore } from '../stores/authStore';

const email = ref('');
const password = ref('');
const show = ref(false);
const error = ref('');
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

function useDemoAccount() {
  email.value = 'teacher@giaoly.test';
  password.value = 'password';
  error.value = '';
}

async function submit() {
  error.value = '';
  try {
    await auth.login(email.value, password.value);
    const target = typeof route.query.redirect === 'string' ? route.query.redirect : '/dashboard';
    await router.push(target);
  } catch (e) {
    const response = (e as { response?: { data?: { message?: string } } }).response;
    error.value = response?.data?.message ?? 'Không thể đăng nhập. Vui lòng kiểm tra lại thông tin.';
  }
}
</script>

<template>
  <main class="login-page">
    <section class="login-visual" aria-label="Giới thiệu Hành Trang Đức Tin">
      <div class="visual-orb visual-orb-one"/>
      <div class="visual-orb visual-orb-two"/>

      <RouterLink class="visual-brand" to="/">
        <span class="brand-logo"><img :src="'/images/02_individual_assets/logo-icon.png'" alt=""></span>
        <span><b>Hành Trang Đức Tin</b><small>Giáo xứ Cái Răng</small></span>
      </RouterLink>

      <div class="visual-copy">
        <span class="eyebrow"><ShieldCheck :size="16"/> Không gian học giáo lý an toàn</span>
        <h1>Đồng hành trên hành trình<br><em>lớn lên trong đức tin</em></h1>
        <p>Kết nối giáo lý viên, phụ huynh và thiếu nhi trong một môi trường gần gũi, thuận tiện và đầy yêu thương.</p>
        <div class="visual-benefits">
          <span><CheckCircle2/> Theo dõi tiến độ</span>
          <span><CheckCircle2/> Kết nối lớp học</span>
          <span><CheckCircle2/> Đồng hành mỗi ngày</span>
        </div>
      </div>

      <img class="visual-church" :src="'/images/02_individual_assets/church-hero.png'" alt="">
      <img class="visual-children" :src="'/images/02_individual_assets/hero-children-group.png'" alt="Các em thiếu nhi giáo lý">
      <p class="visual-quote">“Hãy để trẻ em đến với Thầy.” <span>— Mc 10,14</span></p>
    </section>

    <section class="login-content">
      <div class="content-orb"/>
      <RouterLink class="back-home" to="/"><ArrowLeft :size="16"/> Về trang chủ</RouterLink>

      <form class="login-card" @submit.prevent="submit">
        <div class="mobile-brand">
          <span class="brand-logo"><img :src="'/images/02_individual_assets/logo-icon.png'" alt=""></span>
          <span><b>Hành Trang Đức Tin</b><small>Giáo xứ Cái Răng</small></span>
        </div>

        <div class="form-heading">
          <span class="welcome-icon"><LockKeyhole :size="23"/></span>
          <div>
            <p>Chào mừng trở lại</p>
            <h2>Đăng nhập hệ thống</h2>
          </div>
        </div>
        <p class="form-intro">Nhập thông tin tài khoản để tiếp tục hành trình của bạn.</p>

        <p
          v-if="route.query.reason === 'expired'"
          role="status"
          class="session-message"
        >
          Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.
        </p>
        <p v-if="error" role="alert" class="error-message">{{ error }}</p>

        <label class="field">
          <span>Email</span>
          <span class="input-wrap">
            <Mail :size="18"/>
            <input
              v-model="email"
              type="email"
              required
              autocomplete="email"
              placeholder="name@example.com"
            >
          </span>
        </label>

        <label class="field">
          <span>Mật khẩu</span>
          <span class="input-wrap">
            <LockKeyhole :size="18"/>
            <input
              v-model="password"
              :type="show ? 'text' : 'password'"
              required
              autocomplete="current-password"
              placeholder="Nhập mật khẩu"
            >
            <button
              type="button"
              class="password-toggle"
              :aria-label="show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
              @click="show = !show"
            >
              <EyeOff v-if="show" :size="19"/>
              <Eye v-else :size="19"/>
            </button>
          </span>
        </label>

        <button :disabled="auth.isSubmitting" class="submit-button">
          <span>{{ auth.isSubmitting ? 'Đang đăng nhập…' : 'Đăng nhập' }}</span>
          <ArrowRight v-if="!auth.isSubmitting" :size="18"/>
          <span v-else class="spinner"/>
        </button>

        <div class="demo-account">
          <div>
            <b>Tài khoản trải nghiệm</b>
            <span>teacher@giaoly.test · password</span>
          </div>
          <button type="button" @click="useDemoAccount">Điền nhanh</button>
        </div>

        <p class="support-note">Bạn cần hỗ trợ? Liên hệ Ban Giáo lý Giáo xứ Cái Răng.</p>
      </form>
    </section>
  </main>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap');

.login-page{
  --blue:#0767d2;
  --deep-blue:#063e87;
  --ink:#082f68;
  min-height:100vh;
  min-height:100svh;
  display:grid;
  grid-template-columns:minmax(520px,.98fr) minmax(520px,1.02fr);
  gap:16px;
  padding:16px;
  overflow:hidden;
  background:#eef6ff;
  color:#193b67;
  font-family:'Nunito',sans-serif;
}
.login-page *,
.login-page *::before,
.login-page *::after{
  box-sizing:border-box;
}
.login-visual{
  position:relative;
  min-height:calc(100svh - 32px);
  overflow:hidden;
  isolation:isolate;
  display:flex;
  flex-direction:column;
  border-radius:30px;
  padding:44px 48px;
  color:#fff;
  background:
    radial-gradient(circle at 78% 23%,rgba(92,170,255,.35),transparent 31%),
    radial-gradient(circle at 18% 80%,rgba(53,146,244,.3),transparent 32%),
    linear-gradient(145deg,#062f6b 0%,#064b9e 54%,#0873d7 100%);
  box-shadow:0 24px 60px rgba(6,55,118,.18);
}
.login-visual::after{
  content:'';
  position:absolute;
  z-index:-2;
  left:-10%;
  right:-10%;
  bottom:-29%;
  height:59%;
  border-radius:50% 50% 0 0;
  background:linear-gradient(180deg,rgba(255,255,255,.15),rgba(255,255,255,.04));
  transform:rotate(-3deg);
}
.visual-orb{
  position:absolute;
  z-index:-1;
  border:1px solid rgba(255,255,255,.15);
  border-radius:50%;
}
.visual-orb-one{width:360px;height:360px;right:-145px;top:-130px}
.visual-orb-two{width:230px;height:230px;left:-100px;bottom:160px}
.visual-brand,.mobile-brand{
  display:flex;
  align-items:center;
  gap:11px;
}
.visual-brand{width:max-content;color:#fff}
.brand-logo{
  width:48px;
  height:48px;
  flex:none;
  display:grid;
  place-items:center;
  overflow:hidden;
  border-radius:15px;
  background:rgba(255,255,255,.14);
  backdrop-filter:blur(8px);
}
.brand-logo img{width:72px;height:72px;max-width:none;object-fit:contain}
.visual-brand b,.visual-brand small,.mobile-brand b,.mobile-brand small{display:block}
.visual-brand b{font-size:17px;font-weight:900}
.visual-brand small{font-size:11px;color:#cfe7ff}
.visual-copy{
  position:relative;
  z-index:3;
  max-width:610px;
  margin-top:clamp(70px,12vh,145px);
}
.eyebrow{
  display:inline-flex;
  align-items:center;
  gap:7px;
  border:1px solid rgba(255,255,255,.25);
  border-radius:999px;
  padding:8px 13px;
  background:rgba(255,255,255,.1);
  color:#e8f5ff;
  font-size:12px;
  font-weight:800;
  backdrop-filter:blur(8px);
}
.visual-copy h1{
  margin-top:20px;
  font-size:clamp(35px,3vw,52px);
  font-weight:900;
  line-height:1.12;
  letter-spacing:-.8px;
}
.visual-copy h1 em{color:#ffd247;font-style:normal}
.visual-copy>p{
  max-width:530px;
  margin-top:18px;
  color:#d9ecff;
  font-size:15px;
  line-height:1.7;
}
.visual-benefits{
  display:flex;
  flex-wrap:wrap;
  gap:10px 18px;
  margin-top:24px;
  color:#eef8ff;
  font-size:12px;
  font-weight:700;
}
.visual-benefits span{display:flex;align-items:center;gap:6px}
.visual-benefits svg{width:16px;color:#ffd247}
.visual-church,.visual-children{
  position:absolute;
  z-index:1;
  pointer-events:none;
  object-fit:contain;
}
.visual-church{
  right:-150px;
  bottom:-165px;
  width:610px;
  opacity:.65;
  filter:drop-shadow(0 12px 22px rgba(0,35,78,.15));
}
.visual-children{
  left:clamp(95px,10vw,190px);
  bottom:-85px;
  width:min(680px,75%);
  filter:drop-shadow(0 18px 18px rgba(0,34,75,.25));
}
.visual-quote{
  position:absolute;
  z-index:4;
  left:48px;
  bottom:35px;
  font-size:12px;
  font-weight:700;
  color:#e2f1ff;
}
.visual-quote span{color:#ffdb62}
.login-content{
  position:relative;
  min-width:0;
  display:grid;
  place-items:center;
  padding:64px 40px;
  border-radius:30px;
  background:
    radial-gradient(circle at 90% 10%,rgba(77,154,241,.12),transparent 27%),
    linear-gradient(145deg,#f8fbff,#edf6ff);
}
.content-orb{
  position:absolute;
  width:260px;
  height:260px;
  right:-80px;
  bottom:-100px;
  border:45px solid rgba(39,125,221,.05);
  border-radius:50%;
}
.back-home{
  position:absolute;
  top:30px;
  right:35px;
  display:flex;
  align-items:center;
  gap:7px;
  border-radius:999px;
  padding:9px 13px;
  color:#44617f;
  font-size:12px;
  font-weight:800;
  transition:.2s ease;
}
.back-home:hover{background:#fff;color:var(--blue);box-shadow:0 8px 25px rgba(19,79,143,.1)}
.login-card{
  position:relative;
  z-index:2;
  width:min(100%,470px);
  border:1px solid rgba(197,218,240,.8);
  border-radius:28px;
  padding:42px;
  background:rgba(255,255,255,.94);
  box-shadow:0 24px 65px rgba(15,70,132,.13);
}
.mobile-brand{display:none}
.form-heading{
  display:flex;
  align-items:center;
  gap:13px;
}
.welcome-icon{
  width:50px;
  height:50px;
  flex:none;
  display:grid;
  place-items:center;
  border-radius:16px;
  background:#eaf4ff;
  color:var(--blue);
}
.form-heading p{
  color:#67809c;
  font-size:12px;
  font-weight:700;
}
.form-heading h2{
  margin-top:2px;
  color:var(--ink);
  font-size:25px;
  font-weight:900;
  line-height:1.2;
}
.form-intro{
  margin:17px 0 25px;
  color:#637b95;
  font-size:13px;
  line-height:1.55;
}
.session-message{
  margin:-8px 0 18px;
  border:1px solid #bfdbfe;
  border-radius:12px;
  padding:10px 12px;
  background:#eff6ff;
  color:#1d4ed8;
  font-size:12px;
}
.error-message{
  margin:-8px 0 18px;
  border:1px solid #fecdd3;
  border-radius:12px;
  padding:10px 12px;
  background:#fff1f2;
  color:#be123c;
  font-size:12px;
}
.field{display:block;margin-top:18px}
.field>span:first-child{
  display:block;
  margin-bottom:8px;
  color:#173b67;
  font-size:13px;
  font-weight:800;
}
.input-wrap{
  height:50px;
  display:flex;
  align-items:center;
  gap:10px;
  border:1px solid #cbdcec;
  border-radius:13px;
  padding:0 14px;
  background:#fbfdff;
  color:#8195aa;
  transition:border-color .2s,box-shadow .2s,background .2s;
}
.input-wrap:focus-within{
  border-color:#3b8deb;
  background:#fff;
  box-shadow:0 0 0 4px rgba(59,141,235,.1);
  color:var(--blue);
}
.input-wrap input{
  min-width:0;
  flex:1;
  border:0;
  outline:0;
  background:transparent;
  color:#173b67;
  font:inherit;
  font-size:14px;
}
.input-wrap input::placeholder{color:#9aabba}
.password-toggle{
  display:grid;
  place-items:center;
  margin-right:-5px;
  border-radius:8px;
  padding:6px;
  color:#71869d;
}
.password-toggle:hover{background:#eef6ff;color:var(--blue)}
.submit-button{
  width:100%;
  height:50px;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  margin-top:27px;
  border-radius:13px;
  background:linear-gradient(100deg,#0868d4,#2485eb);
  color:#fff;
  font-size:14px;
  font-weight:900;
  box-shadow:0 12px 24px rgba(18,112,215,.22);
  transition:transform .2s,box-shadow .2s,opacity .2s;
}
.submit-button:hover:not(:disabled){transform:translateY(-1px);box-shadow:0 15px 28px rgba(18,112,215,.28)}
.submit-button:disabled{cursor:not-allowed;opacity:.65}
.spinner{
  width:16px;
  height:16px;
  border:2px solid rgba(255,255,255,.45);
  border-top-color:#fff;
  border-radius:50%;
  animation:spin .7s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}
.demo-account{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  margin-top:20px;
  border:1px dashed #b9d4ef;
  border-radius:13px;
  padding:11px 12px;
  background:#f4f9ff;
}
.demo-account b,.demo-account span{display:block}
.demo-account b{color:#315678;font-size:11px}
.demo-account span{margin-top:2px;color:#71869d;font-size:10px}
.demo-account button{
  flex:none;
  border-radius:8px;
  padding:7px 9px;
  background:#e1efff;
  color:#0860c3;
  font-size:10px;
  font-weight:900;
}
.support-note{
  margin-top:20px;
  color:#8495a8;
  text-align:center;
  font-size:10px;
}

@media(max-width:1200px){
  .login-page{grid-template-columns:minmax(460px,.9fr) minmax(480px,1.1fr)}
  .login-visual{padding-inline:38px}
  .visual-copy h1{font-size:38px}
  .visual-children{left:55px;width:80%}
}

@media(max-width:1023px){
  .login-page{
    display:block;
    overflow:auto;
    padding:18px;
    background:
      radial-gradient(circle at 90% 5%,rgba(44,135,236,.16),transparent 27%),
      linear-gradient(150deg,#eaf5ff,#f8fbff 55%,#edf6ff);
  }
  .login-visual{display:none}
  .login-content{
    min-height:calc(100svh - 36px);
    padding:72px 28px 38px;
    background:transparent;
  }
  .mobile-brand{
    display:flex;
    margin-bottom:30px;
  }
  .mobile-brand .brand-logo{background:#eaf4ff}
  .mobile-brand b{color:var(--ink);font-size:16px;font-weight:900}
  .mobile-brand small{color:#66809a;font-size:10px;font-weight:700}
  .back-home{top:20px;right:20px}
}

@media(max-width:560px){
  .login-page{padding:0;background:#fff}
  .login-content{
    min-height:100svh;
    display:block;
    padding:28px 18px;
    border-radius:0;
    background:
      linear-gradient(180deg,#edf7ff 0,#fff 245px);
  }
  .content-orb{display:none}
  .back-home{
    position:static;
    width:max-content;
    margin:0 0 26px auto;
    padding:7px 0;
  }
  .login-card{
    width:100%;
    border:0;
    border-radius:22px;
    padding:26px 22px 30px;
    box-shadow:0 18px 45px rgba(23,83,143,.11);
  }
  .mobile-brand{margin-bottom:26px}
  .form-heading h2{font-size:23px}
  .form-intro{margin-bottom:23px}
}

@media(max-width:360px){
  .login-content{padding-inline:12px}
  .login-card{padding-inline:18px}
  .demo-account{align-items:flex-start;flex-direction:column}
  .demo-account button{width:100%}
}
</style>
