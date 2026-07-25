import axios,{AxiosError} from 'axios';
const client=axios.create({baseURL:import.meta.env.VITE_API_URL??'/api',withCredentials:true,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
client.interceptors.response.use(r=>r,async(error:AxiosError)=>{if(error.response?.status===401&&location.pathname!=='/login')window.dispatchEvent(new CustomEvent('auth:expired'));return Promise.reject(error);});
export default client;
