import { createRouter, createWebHistory } from 'vue-router';
import DashboardView from '../views/DashboardView.vue';
import ClassesView from '../views/ClassesView.vue';
import AttendanceView from '../views/AttendanceView.vue';
import AssignmentsView from '../views/AssignmentsView.vue';
import ChildrenView from '../views/ChildrenView.vue';
export default createRouter({ history:createWebHistory(), routes:[
 {path:'/',component:DashboardView,meta:{title:'Tổng quan'}},{path:'/lop-hoc',component:ClassesView,meta:{title:'Lớp học'}},{path:'/diem-danh',component:AttendanceView,meta:{title:'Điểm danh'}},{path:'/bai-tap',component:AssignmentsView,meta:{title:'Bài tập'}},{path:'/thieu-nhi',component:ChildrenView,meta:{title:'Thiếu nhi'}},
]});
