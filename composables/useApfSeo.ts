interface SeoInput {
  title: string
  description: string
  path: string
  image?: string
}

export function useApfSeo(input: SeoInput) {
  const config = useRuntimeConfig()
  const baseUrl = config.public.siteUrl
  const canonical = `${baseUrl}${input.path}`
  const image = input.image || 'https://apfpress.com/wp-content/uploads/2026/04/apftypewriter-1024x1024.png'

  useSeoMeta({
    title: input.title,
    description: input.description,
    ogTitle: input.title,
    ogDescription: input.description,
    ogUrl: canonical,
    ogSiteName: 'APF Press',
    ogType: 'website',
    ogImage: image,
    twitterCard: 'summary_large_image'
  })

  useHead({
    link: [{ rel: 'canonical', href: canonical }],
    script: [
      {
        type: 'application/ld+json',
        innerHTML: JSON.stringify({
          '@context': 'https://schema.org',
          '@type': 'Organization',
          name: 'APF Press',
          url: baseUrl,
          logo: 'https://apfpress.com/wp-content/uploads/2025/07/apf_logo_2-orig.png',
          contactPoint: {
            '@type': 'ContactPoint',
            telephone: '+1-416-817-1266',
            contactType: 'customer service',
            email: 'apf.press@rogers.com'
          }
        })
      }
    ]
  })
}
