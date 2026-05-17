<script setup lang="ts">
useApfSeo({
  title: 'Editorial Board | APF Press',
  description: 'Meet the APF Press editorial board, composed of respected scholars and professionals guiding rigorous academic publishing.',
  path: '/editorial-board/'
})

const boardMembers = [
  { name: 'Brad Chilton', affiliation: 'Public Administration Program, Department of Political Science, University of Texas at El Paso, USA.' },
  { name: 'Wesley Crichlow', affiliation: 'Department of Criminology and Justice, Ontario Tech University, Canada.' },
  { name: 'Thomas Fleming', affiliation: 'Criminology and Contemporary Studies, Wilfrid Laurier University, Canada.' },
  { name: 'James F. Hodgson', affiliation: 'Averett University, Virginia, USA.' },
  { name: 'Dr. Morris Jenkins', affiliation: 'Southwestern Law School, California.' },
  { name: 'Stephen Muzzatti', affiliation: 'Department of Sociology, Toronto Metropolitan University, Canada.' },
  { name: 'Catherine Orban', affiliation: 'Department of Criminal Justice, Marygrove College, USA.' },
  { name: 'Jerry Elarick Persaud', affiliation: 'Department of Ethnic Studies, State University of New York at New Paltz, USA.' },
  { name: 'Giorgos Skoulas', affiliation: 'Department of International & European Studies, University of Macedonia, Greece.' },
  { name: 'Ron Stansfield', affiliation: 'Department of Sociology & Anthropology, University of Guelph, Canada.' }
]
</script>

<template>
  <div>
    <PageHero
      eyebrow="Editorial Board"
      title="Guided by Expertise and Integrity"
      text="Our editorial board is composed of respected scholars and professionals dedicated to maintaining high standards in academic publishing."
    />

    <section class="content-band">
      <VContainer>
        <div class="section-heading">
          <p class="eyebrow">
            APF Press
          </p>
          <h1>Editorial Board</h1>
          <p>
            Their guidance helps ensure each publication reflects rigorous research, critical insight, and meaningful contribution to scholarly discourse.
          </p>
        </div>

        <div class="board-grid">
          <article v-for="member in boardMembers" :key="member.name" class="board-member">
            <h2>{{ member.name }}</h2>
            <p>{{ member.affiliation }}</p>
          </article>
        </div>
      </VContainer>
    </section>
  </div>
</template>
