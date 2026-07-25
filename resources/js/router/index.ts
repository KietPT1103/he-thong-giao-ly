import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import DashboardView from '../views/DashboardView.vue';
import ClassesView from '../views/ClassesView.vue';
import AttendanceView from '../views/AttendanceView.vue';
import AssignmentsView from '../views/AssignmentsView.vue';
import ChildrenView from '../views/ChildrenView.vue';
import LoginView from '../views/LoginView.vue';
import ForbiddenView from '../views/ForbiddenView.vue';
import NotFoundView from '../views/NotFoundView.vue';
import { useAuthStore } from '../stores/authStore';

const routes: RouteRecordRaw[] = [
 {path:'/login',component:LoginView,meta:{public:true,title:'Đăng nhập'}},
 {path:'/',component:DashboardView,meta:{requiresAuth:true,title:'Tổng quan'}},
 {path:'/lop-hoc',component:ClassesView,meta:{requiresAuth:true,title:'Lớp học'}},
 {path:'/diem-danh',component:AttendanceView,meta:{requiresAuth:true,title:'Điểm danh'}},
 {path:'/bai-tap',component:AssignmentsView,meta:{requiresAuth:true,title:'Bài tập'}},
 {path:'/thieu-nhi',component:ChildrenView,meta:{requiresAuth:true,title:'Thiếu nhi'}},
 {path:'/lich-hoc',component:ClassesView,meta:{requiresAuth:true,title:'Lịch học'}},
 {path:'/403',component:ForbiddenView,meta:{public:true,title:'Không có quyền'}},
 {path:'/:pathMatch(.*)*',component:NotFoundView,meta:{public:true,title:'Không tìm thấy trang'}},
];
const router=createRouter({history:createWebHistory(),routes});
router.beforeEach(async(to)=>{const auth=useAuthStore();if(!auth.initialized)await auth.initialize();if(to.meta.requiresAuth&&!auth.isAuthenticated)return {path:'/login',query:{redirect:to.fullPath}};if(to.path==='/login'&&auth.isAuthenticated)return '/';return true;});
export default router;
