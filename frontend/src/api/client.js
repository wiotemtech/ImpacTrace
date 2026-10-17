import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('meal_token')
  if (token) config.headers.Authorization = `Bearer ${token}`

  const selectedOrgId = localStorage.getItem('meal_selected_org')
  if (selectedOrgId) {
    config.headers['X-Organization-Id'] = selectedOrgId
  }

  return config
})

api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      const isPublicRequest = String(err.config?.url || '').startsWith('/public/')
      if (isPublicRequest) {
        localStorage.removeItem('public_token')
        return Promise.reject(err)
      }
      localStorage.removeItem('meal_token')
      localStorage.removeItem('meal_user')
      window.location.href = '/login'
    }
    return Promise.reject(err)
  }
)

export default api
