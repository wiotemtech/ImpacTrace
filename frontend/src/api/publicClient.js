import axios from 'axios'

const publicApi = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
})

publicApi.interceptors.request.use((config) => {
  const token = localStorage.getItem('public_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

publicApi.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) localStorage.removeItem('public_token')
    return Promise.reject(error)
  },
)

export default publicApi
